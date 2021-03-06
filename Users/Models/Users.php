<?php

namespace Modules\Users\Models;

use Modules\UserBalanceHistories\Models\UserBalanceHistories;
use Modules\UserGameTokenHistories\Models\UserGameTokenHistories;

class Users extends \App\User
{
    use \Modules\Users\Traits\AttributesTrait;
    use \Modules\Users\Traits\UsermetasTrait;
    use \Spatie\Permission\Traits\HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'password',
        'access_token',
        'verified',
        'verification_code',
        'date_of_birth',
        'gender',
        'address',
        'store_id',
        'balance',
        'game_token',
    ];

    protected $guard_name = 'web';

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    protected static function boot()
    {
        parent::boot();

        self::saved(function ($model) {
            \Cache::forget('users-'.$model->id);
            \Cache::forget('users-usermetas-'.$model->id);
        });

        self::deleted(function ($model) {
            $model->userAddresses->each(function ($userAddress) { $userAddress->delete(); });
            $model->userSocialites->each(function ($userSocialite) { $userSocialite->delete(); });
            \Cache::forget('users-'.$model->id);
            \Cache::forget('users-usermetas-'.$model->id);
        });
    }

    public function getProfileCompleted()
    {
        $completed = 1;

        $completed = empty($this->name) ? 0 : $completed;
        $completed = empty($this->email) ? 0 : $completed;
        $completed = empty($this->phone_number) ? 0 : $completed;
        $completed = empty($this->date_of_birth) ? 0 : $completed;
        $completed = empty($this->address) ? 0 : $completed;

        return $completed;
    }

    public function scopeSearch($query, $params)
    {
        isset($params['id']) ? $query->where('id', $params['id']) : '';
        isset($params['id_in']) ? $query->whereIn('id', $params['id_in']) : '';
        isset($params['name']) ? $query->where('name', 'like', '%'.$params['name'].'%') : '';
        isset($params['email']) ? $query->where('email', 'like', '%'.$params['email'].'%') : '';
        isset($params['phone_number_like']) ? $query->where('phone_number', 'like', '%'.$params['phone_number_like'].'%') : '';
        isset($params['verified']) ? $query->where('verified', $params['verified']) : '';
        isset($params['store_id']) ? $query->where('store_id', $params['store_id']) : '';
        if (isset($params['balance'])) {
            if (isset($params['balance_operator'])) {
                $query->where('balance', $params['balance_operator'], $params['balance']);
            } else {
                $query->where('balance', $params['balance']);
            }
        }
        if (isset($params['game_token'])) {
            if (isset($params['game_token_operator'])) {
                $query->where('game_token', $params['game_token_operator'], $params['game_token']);
            } else {
                $query->where('game_token', $params['game_token']);
            }
        }

        // roles
        isset($params['role_id']) ? $query->whereHas('roles', function ($query) use ($params) { $query->where('id', $params['role_id']); }) : '';
        isset($params['role_name']) ? $query->whereHas('roles', function ($query) use ($params) { $query->where('name', $params['role_name']); }) : '';
        if (isset($params['sort']) && $sort = explode(':', $params['sort'])) {
            $query->orderBy(
                $sort[0],
                isset($sort[1]) ? $sort[1] : null
            );
        }

        return $query;
    }

    public function store()
    {
        return $this->belongsTo(self::class, 'store_id');
    }

    public function storeUsers()
    {
        return $this->hasMany(self::class, 'store_id');
    }

    public function syncPermissions(...$permissions)
    {
        $this->permissions()->detach();
        if ($permissions = array_filter($permissions)) {
            return $this->givePermissionTo($permissions);
        }
        return $this;
    }

    public function syncRoles(...$roles)
    {
        $this->roles()->detach();
        if ($roles = array_filter($roles)) {
            return $this->assignRole($roles);
        }
        return $this;
    }

    public function userAddress()
    {
        return $this->hasOne('\Modules\UserAddresses\Models\UserAddresses', 'user_id')->orderBy('primary', 'desc')->latest();
    }

    public function userAddresses()
    {
        return $this->hasMany('\Modules\UserAddresses\Models\UserAddresses', 'user_id')->orderBy('primary', 'desc')->latest();
    }

    public function userBalanceHistories()
    {
        return $this->hasMany(UserBalanceHistories::class, 'user_id')->orderBy('created_at', 'desc')->latest();
    }

    public function userBalanceHistoryCreate($data = [])
    {
        if ($this->balance != $this->getOriginal('balance')) {
            $data['balance_start'] = $this->getOriginal('balance');
            $data['balance'] = $this->balance - $this->getOriginal('balance');
            $data['balance_end'] = $this->balance;
            $this->userBalanceHistories()->save(new UserBalanceHistories($data));
        }

        return $this;
    }

    public function userGameTokenHistories()
    {
        return $this->hasMany(UserGameTokenHistories::class, 'user_id')->orderBy('created_at', 'desc')->latest();
    }

    public function userGameTokenHistoryCreate($data = [])
    {
        if ($this->game_token != $this->getOriginal('game_token')) {
            $data['game_token_start'] = $this->getOriginal('game_token');
            $data['game_token'] = $this->game_token - $this->getOriginal('game_token');
            $data['game_token_end'] = $this->game_token;
            $this->userGameTokenHistories()->save(new UserGameTokenHistories($data));
        }

        return $this;
    }

    public function userGames()
    {
        return $this->hasMany('\App\Http\Models\Cnr\UsersGames', 'user_id', 'id');
    }

    public function usermetas()
    {
        return $this->hasMany('\Modules\Usermetas\Models\Usermetas', 'user_id');
    }

    public function userSocialites()
    {
        return $this->hasMany('\Modules\UserSocialites\Models\UserSocialites', 'user_id')->orderBy('provider');
    }
}
