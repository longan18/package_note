<?php

namespace App\Domains\Auth\Models\Traits\Attribute;

use App\Domains\Auth\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Trait UserAttribute.
 */
trait UserAttribute
{
    /**
     * @param $password
     */
    public function setPasswordAttribute($password): void
    {
        // If password was accidentally passed in already hashed, try not to double hash it
        // Note: Password Histories are logged from the \App\Domains\Auth\Observer\UserObserver class
        $this->attributes['password'] =
            (strlen($password) === 60 && preg_match('/^\$2y\$/', $password)) ||
            (strlen($password) === 95 && preg_match('/^\$argon2i\$/', $password)) ?
                $password :
                Hash::make($password);
    }

    /**
     * @return mixed
     */
    public function getImageAttribute()
    {
        return $this->getAvatar();
    }

    /**
     * @return string
     */
    public function getPermissionsLabelAttribute()
    {
        if ($this->hasAllAccess()) {
            return 'All';
        }

        if (!$this->permissions->count()) {
            return 'None';
        }

        return collect($this->getPermissionDescriptions())
            ->implode('<br/>');
    }

    /**
     * @return string
     */
    public function getRolesLabelAttribute()
    {
        if ($this->hasAllAccess()) {
            return 'All';
        }

        if (!$this->roles->count()) {
            return 'None';
        }

        return collect($this->getRoleNames())
            ->each(function ($role) {
                return ucwords($role);
            })
            ->implode('<br/>');
    }

    /**
     * @return string
     */
    public function getAvatarUserAttribute($tag)
    {
        $mediaAvatarTag = $this->getMedia(User::TAG_AVATAR)->last();

        switch ($tag) {
            case(SCREEN_EDIT):
                $avatar = asset('img/icon/avatar.svg');
                break;
            default:
                $avatar = asset('img/brand/avatar-default.svg');
        }

        return $mediaAvatarTag ? $mediaAvatarTag->getUrl() : $avatar;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getAvaliablePermissionsAttribute()
    {
        $rolePermissions = $this->getPermissionsViaRoles();
        $additionalPermissions = $this->loadMissing('rolesConcurrently', 'rolesConcurrently.permissions')
            ->rolesConcurrently->flatMap(function ($role) {
                return $role->permissions;
            })->sort()->values();

        return $rolePermissions->merge($additionalPermissions)->unique('id');
    }

    /**
     * @return mixed|null
     */
    public function getRoleIdOfUserAttribute(): mixed
    {
        $relationship = false;
        if ($this->isCoordinator()) {
            $relationship = 'coordinator';
        } elseif ($this->isBroker()) {
            $relationship = 'broker';
        } elseif ($this->isExpert()) {
            $relationship = 'expert';
        } elseif ($this->isLeadership()) {
            $relationship = 'leadershipInfo';
        } elseif ($this->isLeadership()) {
            $relationship = 'leadershipInfo';
        } elseif (!$this->hasAllAccess()) {
            $relationship = 'otherRole';
        }

        if ($relationship) {
            return $this->$relationship->id;
        }

        return null;
    }
}
