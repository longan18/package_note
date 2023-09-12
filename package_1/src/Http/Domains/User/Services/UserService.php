<?php

namespace App\Domains\Auth\Services;

use App\Domains\Auth\Events\User\UserCreated;
use App\Domains\Auth\Events\User\UserDeleted;
use App\Domains\Auth\Events\User\UserDestroyed;
use App\Domains\Auth\Events\User\UserRestored;
use App\Domains\Auth\Events\User\UserStatusChanged;
use App\Domains\Auth\Events\User\UserUpdated;
use App\Domains\Auth\Models\User;
use App\Domains\Media\Services\MediaService;
use App\Exceptions\GeneralException;
use App\Services\BaseService;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Class UserService.
 */
class UserService extends BaseService
{

    protected MediaService $mediaService;

    /**
     * UserService constructor.
     *
     * @param User $user
     * @param MediaService $mediaService
     */
    public function __construct(
        User $user,
        MediaService $mediaService
    )
    {
        $this->model = $user;
        $this->mediaService = $mediaService;
    }

    /**
     * @param $type
     * @param  bool|int  $perPage
     * @return mixed
     */
    public function getByType($type, $perPage = false)
    {
        if (is_numeric($perPage)) {
            return $this->model::byType($type)->paginate($perPage);
        }

        return $this->model::byType($type)->get();
    }

    /**
     * @param  array  $data
     * @return mixed
     *
     * @throws GeneralException
     */
    public function registerUser(array $data = []): User
    {
        DB::beginTransaction();

        try {
            $user = $this->createUser($data);
        } catch (Exception $e) {
            DB::rollBack();

            throw new GeneralException(__('There was a problem creating your account.'));
        }

        DB::commit();

        return $user;
    }

    /**
     * @param $info
     * @param $provider
     * @return mixed
     *
     * @throws GeneralException
     */
    public function registerProvider($info, $provider): User
    {
        $user = $this->model::where('provider_id', $info->id)->first();

        if (!$user) {
            DB::beginTransaction();

            try {
                $user = $this->createUser([
                    'name' => $info->name,
                    'email' => $info->email,
                    'provider' => $provider,
                    'provider_id' => $info->id,
                    'email_verified_at' => now(),
                ]);
            } catch (Exception $e) {
                DB::rollBack();

                throw new GeneralException(__('There was a problem connecting to :provider', ['provider' => $provider]));
            }

            DB::commit();
        }

        return $user;
    }

    /**
     * @param  array  $data
     * @return User
     *
     * @throws GeneralException
     * @throws \Throwable
     */
    public function store(array $data = []): User
    {
        DB::beginTransaction();

        try {
            $user = $this->createUser([
                'type' => $data['type'],
                'name' => $data['name'],
                'phone_number' => $data['phone_number'],
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
                'active' => isset($data['active']) && $data['active'] === '1',
            ]);

            $user->syncRoles($data['roles'] ?? []);

            if (!config('base.access.user.only_roles')) {
                $user->syncPermissions($data['permissions'] ?? []);
            }
        } catch (Exception $e) {
            DB::rollBack();

            throw new GeneralException(__('There was a problem creating this user. Please try again.'));
        }

        event(new UserCreated($user));

        DB::commit();

        // They didn't want to auto verify the email, but do they want to send the confirmation email to do so?
        if (!isset($data['email_verified']) && isset($data['send_confirmation_email']) && $data['send_confirmation_email'] === '1') {
            $user->sendEmailVerificationNotification();
        }

        return $user;
    }

    /**
     * @param  User  $user
     * @param  array  $data
     * @return User
     *
     * @throws \Throwable
     */
    public function update(User $user, array $data = []): User
    {
        DB::beginTransaction();

        try {
            $user->update([
                'type' => $user->isMasterAdmin() ? $this->model::TYPE_ADMIN : $data['type'] ?? $user->type,
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if (!$user->isMasterAdmin()) {
                // Replace selected roles/permissions
                $user->syncRoles($data['roles'] ?? []);

                if (!config('base.access.user.only_roles')) {
                    $user->syncPermissions($data['permissions'] ?? []);
                }
            }
        } catch (Exception $e) {
            DB::rollBack();

            throw new GeneralException(__('There was a problem updating this user. Please try again.'));
        }

        event(new UserUpdated($user));

        DB::commit();

        return $user;
    }

    /**
     * @param  User  $user
     * @param  array  $data
     * @return User
     */
    public function updateProfile(User $user, array $data = []): User
    {
        $user->name = $data['name'] ?? null;

        if ($user->canChangeEmail() && $user->email !== $data['email']) {
            $user->email = $data['email'];
            $user->email_verified_at = null;
            $user->sendEmailVerificationNotification();
            session()->flash('resent', true);
        }

        return tap($user)->save();
    }

    /**
     * @param  User  $user
     * @param $data
     * @param  bool  $expired
     * @return User
     *
     * @throws \Throwable
     */
    public function updatePassword(User $user, $data, $expired = false): User
    {
        if (isset($data['current_password'])) {
            throw_if(
                !Hash::check($data['current_password'], $user->password),
                new GeneralException(__('That is not your old password.'))
            );
        }

        // Reset the expiration clock
        if ($expired) {
            $user->password_changed_at = now();
        }

        $user->password = $data['password'];

        return tap($user)->update();
    }

    /**
     * @param  User  $user
     * @param $status
     * @return User
     *
     * @throws GeneralException
     */
    public function mark(User $user, $status): User
    {
        if ($status === 0 && auth()->id() === $user->id) {
            throw new GeneralException(__('You can not do that to yourself.'));
        }

        if ($status === 0 && $user->isMasterAdmin()) {
            throw new GeneralException(__('You can not deactivate the administrator account.'));
        }

        $user->active = $status;

        if ($user->save()) {
            event(new UserStatusChanged($user, $status));

            return $user;
        }

        throw new GeneralException(__('There was a problem updating this user. Please try again.'));
    }

    /**
     * @param  User  $user
     * @return User
     *
     * @throws GeneralException
     */
    public function delete(User $user): User
    {
        if ($user->id === auth()->id()) {
            throw new GeneralException(__('You can not delete yourself.'));
        }

        if ($this->deleteById($user->id)) {
            event(new UserDeleted($user));

            return $user;
        }

        throw new GeneralException('There was a problem deleting this user. Please try again.');
    }

    /**
     * @param  User  $user
     * @return User
     *
     * @throws GeneralException
     */
    public function restore(User $user): User
    {
        if ($user->restore()) {
            event(new UserRestored($user));

            return $user;
        }

        throw new GeneralException(__('There was a problem restoring this user. Please try again.'));
    }

    /**
     * @param  User  $user
     * @return bool
     *
     * @throws GeneralException
     */
    public function destroy(User $user): bool
    {
        if ($user->forceDelete()) {
            event(new UserDestroyed($user));

            return true;
        }

        throw new GeneralException(__('There was a problem permanently deleting this user. Please try again.'));
    }

    /**
     * @param  array  $data
     * @return User
     */
    protected function createUser(array $data = []): User
    {
        return $this->model::create([
            'type' => $data['type'],
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'provider' => $data['provider'] ?? null,
            'provider_id' => $data['provider_id'] ?? null,
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'active' => $data['active'] ?? true,
            'phone_number' => $data['phone_number'],
        ]);
    }

    /**
     * @param Request $request
     * @param array $leaderShipInfo
     * @return User
     */
    public function createUserWithLeadershipRole(Request $request, array $leaderShipInfo)
    {
        $attributes = $this->getAttributesUserRequest($request);
        $attributes['type'] = User::TYPE_ADMIN;
        $attributes['email_verified_at'] = now();

        $user = $this->model->create($attributes);
        throw_if(
            !$user ||
            !$user->leaderShipInfo()->create($leaderShipInfo),
            Exception::class,
            'Create User failed.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        $user->syncRoles(User::ROLE_LEADERSHIP);
        $user->rolesConcurrently()->attach($request->get('roles_concurrently_id'));
        if ($request->get('media')) {
            $user->attachMedia($request->get('media'), User::TAG_AVATAR);
        }

        return $user;
    }

    /**
     * @param Request $request
     * @param array $leaderShipInfo
     * @return Model
     * @throws \Throwable
     */
    public function updateUserWithLeadershipRole(Request $request, array $leaderShipInfo): Model
    {
        $attributes = $this->getAttributesUserRequest($request);
        $user = $this->getById($request->user_id);
        $user->update($attributes);

        throw_if(
            !$user->leaderShipInfo()->update($leaderShipInfo),
            Exception::class,
            'Update User failed.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        $this->handleUpdateAvatarUser($user, $request);

        $user->rolesConcurrently()->sync($request->get('roles_concurrently_id'));

        return $user;
    }

    /**
     * @param $request
     * @return false
     */
    public function insertUserBroker($request)
    {
        try {
            return $this->model::create([
                'type' => User::TYPE_ADMIN,
                'name' => $request['name'],
                'email' => $request['email'],
                'password' => $request['password'],
                'active' => User::IS_ACTIVE,
                'email_verified_at' => now()
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return false;
        }
    }

    /**
     * @param Request $request
     * @param array $expertInfo
     * @return User
     */
    public function createUserWithExpertRole(Request $request, array $expertInfo)
    {
        $attributes = $this->getAttributesUserRequest($request);
        $attributes['type'] = User::TYPE_ADMIN;
        $attributes['email_verified_at'] = now();

        $user = $this->model->create($attributes);
        throw_if(
            !$user ||
            !$user->expert()->create($expertInfo),
            Exception::class,
            'Create User failed.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        $user->syncRoles(User::ROLE_EXPERT);
        $user->rolesConcurrently()->attach($request->get('roles_concurrently_id'));
        if ($request->get('media')) {
            $user->attachMedia($request->get('media'), User::TAG_AVATAR);
        }

        return $user;
    }

    /**
     * @param $user
     * @param $request
     * @return void
     * @throws Exception
     */
    public function handleUpdateAvatarUser($user, $request)
    {
        $deleteMedia = [];
        if($request->hasFile('avatar')) {
            $deleteMedia = $this->mediaService->deleteMedia($user->getMedia(User::TAG_AVATAR));
            $file = $this->mediaService->storeManyFilesTemporary([$request->file('avatar')], config('upload.avatar_folder'), false);
            $user->syncMedia($file->first()->id, User::TAG_AVATAR);
        } else if(isset($request->remove_avatar)) {
            $deleteMedia = $this->mediaService->deleteMedia($user->getMedia(User::TAG_AVATAR));
        }

        if (count($deleteMedia)) {
            $this->mediaService->deleteFileInStorage($deleteMedia);
        }
    }

    /**
     * @param $request
     * @return mixed
     */
    public function getAttributesUserRequest($request)
    {
        return $request->only('name', 'email', 'password', 'phone_number');
    }

    /**
     * @param Request $request
     * @param array $expertInfo
     * @return Model
     * @throws \Throwable
     */
    public function updateUserWithExpertRole(Request $request, array $expertInfo): Model
    {
        $attributes = $this->getAttributesUserRequest($request);
        $user = $this->getById($request->user_id);
        $user->update($attributes);
        throw_if(
            !$user->expert()->update($expertInfo),
            Exception::class,
            'Update User failed.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        $this->handleUpdateAvatarUser($user, $request);
        $user->rolesConcurrently()->sync($request->get('roles_concurrently_id'));
        return $user;
    }

    /**
     * @param Request $request
     * @param array $companyMemberInfo
     * @return Model
     * @throws \Throwable
     */
    public function createUserWithCompanyMember(Request $request, array $companyMemberInfo): Model
    {
        $attributes = $this->getAttributesUserRequest($request);
        $attributes['type'] = User::TYPE_ADMIN;
        $attributes['company_code'] = $companyMemberInfo['code'];
        $attributes['email_verified_at'] = now();

        $user = $this->model->create($attributes);
        $user->assignRole(config('base.access.role.admin'));
        throw_if(
            !$user->companyMember()->create($companyMemberInfo),
            Exception::class,
            'Create User failed.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        return $user;
    }

    /**
     * @param Request $request
     * @param array $otherRoleInfo
     * @return Model
     * @throws \Throwable
     */
    public function updateUserWithOtherRole(Request $request, array $otherRoleInfo): Model
    {
        $attributes = $this->getAttributesUserRequest($request);
        if (is_null($attributes['password'])) {
            unset($attributes['password']);
        }
        $attributes['type'] = User::TYPE_ADMIN;
        $user = $this->getById($request->user_id);
        $user->update($attributes);
        $user->roles()->sync($request->role);
        throw_if(
            !$user->otherRole()->update($otherRoleInfo),
            Exception::class,
            'Update User failed.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        $this->handleUpdateAvatarUser($user, $request);

        return $user;
    }

    /**
     * @param Request $request
     * @param array $companyMemberInfo
     * @return Model
     * @throws \Throwable
     */
    public function updateUserWithCompanyMember(Request $request, array $companyMemberInfo): Model
    {
        $attributes = $this->getAttributesUserRequest($request);
        if (is_null($attributes['password'])) {
            unset($attributes['password']);
        }
        $attributes['type'] = User::TYPE_ADMIN;
        $user = $this->getById($request->user_id);
        $user->update($attributes);
        throw_if(
            !$user->companyMember()->update($companyMemberInfo),
            Exception::class,
            'Update User failed.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        return $user;
    }
}
