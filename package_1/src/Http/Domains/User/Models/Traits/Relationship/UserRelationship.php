<?php

namespace App\Domains\Auth\Models\Traits\Relationship;

use App\Domains\Auth\Models\AdditionalUserRole;
use App\Domains\Auth\Models\Role;
use App\Domains\Broker\Models\Broker;
use App\Domains\CompanyMember\Models\CompanyMember;
use App\Domains\Leadership\Models\Leadership;
use App\Domains\Auth\Models\PasswordHistory;
use App\Domains\Coordinator\Models\Coordinator;
use App\Domains\Customer\Models\Customer;
use App\Domains\Deal\Models\Deal;
use App\Domains\Expert\Models\Expert;
use App\Domains\OtherRole\Models\OtherRole;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Class UserRelationship.
 */
trait UserRelationship
{
    /**
     * @return mixed
     */
    public function passwordHistories()
    {
        return $this->morphMany(PasswordHistory::class, 'model');
    }

    /**
     * @return HasOne
     */
    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * @return HasOne
     */
    public function expert(): HasOne
    {
        return $this->hasOne(Expert::class);
    }

    /**
     * @return HasOne
     */
    public function coordinator(): HasOne
    {
        return $this->hasOne(Coordinator::class);
    }

    /**
     * @return HasOne
     */
    public function leadershipInfo(): HasOne
    {
        return $this->hasOne(Leadership::class, 'user_id', 'id');
    }

    /**
     * @return HasOne
     */
    public function broker(): HasOne
    {
        return $this->hasOne(Broker::class, 'user_id', 'id');
    }

    /**
     * @return BelongsToMany
     */
    public function rolesConcurrently(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, AdditionalUserRole::class, 'user_id', 'role_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function dealsCreated(): HasMany
    {
        return $this->hasMany(Deal::class, 'creator_id', 'id');

    }

    /**
     * @return HasOne
     */
    public function companyMember(): HasOne
    {
        return $this->hasOne(CompanyMember::class);
    }

    /**
     * @return HasOne
     */
    public function otherRole(): HasOne
    {
        return $this->hasOne(OtherRole::class);
    }
}
