<?php

namespace App\Domains\Auth\Models\Traits\Method;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Trait UserMethod.
 */
trait UserMethod
{
    /**
     * @return bool
     */
    public function isMasterAdmin(): bool
    {
        return $this->id === 1;
    }

    /**
     * @return mixed
     */
    public function isAdmin(): bool
    {
        return $this->type === self::TYPE_ADMIN;
    }

    /**
     * @return mixed
     */
    public function isUser(): bool
    {
        return $this->type === self::TYPE_USER;
    }

    /**
     * @return mixed
     */
    public function isCoordinator(): bool
    {
        return $this->isAdmin() && $this->hasRole(self::ROLE_COORDINATOR);
    }

    /**
     * @return mixed
     */
    public function isBroker(): bool
    {
        return $this->isAdmin() && $this->hasRole(self::ROLE_BROKER);
    }

    /**
     * @return mixed
     */
    public function isExpert(): bool
    {
        return $this->isAdmin() && $this->hasRole(self::ROLE_EXPERT);
    }

    /**
     * @return mixed
     */
    public function isLeadership(): bool
    {
        return $this->isAdmin() && $this->hasRole(self::ROLE_LEADERSHIP);
    }

    /**
     * @return mixed
     */
    public function hasAllAccess(): bool
    {
        return $this->isAdmin() && $this->hasRole(config('base.access.role.admin'));
    }

    /**
     * @param string|array $permissions
     * @return bool
     */
    public function customHasPermissions(string|array $permissions): bool
    {
        $permissions = collect($permissions)->flatten();

        return $this->getAvaliablePermissionsAttribute()->whereIn('name', $permissions)->isNotEmpty();
    }

    /**
     * @param $type
     * @return bool
     */
    public function isType($type): bool
    {
        return $this->type === $type;
    }

    /**
     * @return mixed
     */
    public function canChangeEmail(): bool
    {
        return config('base.access.user.change_email');
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @return bool
     */
    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * @return bool
     */
    public function isSocial(): bool
    {
        return $this->provider && $this->provider_id;
    }

    /**
     * @return Collection
     */
    public function getPermissionDescriptions(): Collection
    {
        return $this->permissions->pluck('description');
    }

    /**
     * @param $size
     * @return string
     */
    public function getAvatar($size = null)
    {
        return Storage::disk('public')->url('users/' . $this->avatar);
    }

    /**
     * @return array
     */
    static public function getTypes()
    {
        return [
            self::TYPE_ADMIN,
            self::TYPE_USER,
        ];
    }

    /**
     * @return array
     */
    static public function getDefaultRoles()
    {
        return [
            config('base.access.role.admin'),
            self::ROLE_BROKER,
            self::ROLE_COORDINATOR,
            self::ROLE_EXPERT,
            self::ROLE_LEADERSHIP,
        ];
    }
}
