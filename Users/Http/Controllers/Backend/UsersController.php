<?php

namespace Modules\Users\Http\Controllers\Backend;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Permissions\Models\Permission;
use Modules\Roles\Models\Role;
use Modules\Usermetas\Models\Usermetas;
use Modules\Users\Excel\UserExcel;
use Modules\Users\Models\Users;

class UsersController extends Controller
{
    protected $model;

    public function __construct()
    {
        $this->model = new Users;
    }

    public function index(Request $request)
    {
        $request->query('sort') ?: $request->query->set('sort', 'email:asc');
        $request->query('limit') ?: $request->query->set('limit', config('cms.database.eloquent.model.per_page'));

        $data['model'] = new Users;
        $data['role'] = new Role;
        $data['users'] = Users::with('roles')->search($request->query())->paginate($request->query('limit'));

        if ($indexAction = $this->indexAction($request)) {
            return $indexAction;
        }

        return view('users::backend/index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data['permissions'] = Permission::orderBy('name')->get();
        $data['roles'] = Role::orderBy('name')->get();
        $data['user'] = new Users;
        return view('users::backend/create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(\Modules\Users\Http\Requests\Backend\StoreRequest $request)
    {
        $request->merge(['password' => \Hash::make($request->input('password'))]);

        $user = new Users;
        $user->fill($request->input());
        // $user->userBalanceHistoryCreate(['type' => 'backend_users']);
        $user->save();
        (new Usermetas)->sync($request->input('usermetas'), $user->id);
        auth()->user()->can('backend roles') ? $user->syncRoles($request->input('roles')) : '';
        auth()->user()->can('backend permissions') ? $user->syncPermissions($request->input('permissions')) : '';
        flash(trans('cms::cms.data_has_been_created'))->success()->important();
        return redirect()->back();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data['permissions'] = Permission::orderBy('name')->get();
        $data['roles'] = Role::orderBy('name')->get();
        $data['user'] = Users::findOrFail($id);
        return view('users::backend/edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(\Modules\Users\Http\Requests\Backend\UpdateRequest $request, $id)
    {
        $user = Users::findOrFail($id);
        $request->input('password') ? $request->merge(['password' => \Hash::make($request->input('password'))]) : $request->request->remove('password');
        $user->fill($request->input());
        $user->userBalanceHistoryCreate(['type' => 'backend_users']);
        $user->save();
        (new Usermetas)->sync($request->input('usermetas'), $user->id);
        auth()->user()->can('backend roles') ? $user->syncRoles($request->input('roles')) : '';
        auth()->user()->can('backend permissions') ? $user->syncPermissions($request->input('permissions')) : '';
        flash(trans('cms::cms.data_has_been_updated'))->success()->important();
        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function indexAction(Request $request)
    {
        $users = null;

        if ($actionId = $request->query('action_id')) {
            $users = Users::search($request->query())->whereIn('id', $actionId)->paginate($request->query('limit'));
        }

        if ($request->query('action') == 'delete' && $users) {
            $users->each(function ($user) { $user->delete(); });
            flash(trans('cms::cms.deleted').' ('.$users->count().')')->success()->important();
            return redirect()->back();
        } else if ($request->query('action') == 'export_to_excel' && $users) {
            return \Excel::download(
                new UserExcel($users),
                $this->model->getTable().'-'.date('YmdHis').'.xlsx'
            );
        }
    }

    public function delete($id)
    {
        $user = Users::findOrFail($id);
        $user->syncRoles()->delete($id);
        flash(trans('cms::cms.data_has_been_deleted'))->success()->important();
        return redirect()->back();
    }
}
