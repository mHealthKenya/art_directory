<?php

namespace App\Http\Controllers;

use App\Notifications\UserCreated;
use App\UserGroup;
use App\User;
use App\UserPermission;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    private $_passed = false;
    public $photo;
    protected $random_pass;


    public function __construct() {
        $this->photo = asset('assets/img/default-avatar.png');
        $this->middleware(['auth']);
    }

    public function users() {
        $users = User::all();
        $user_roles = UserGroup::all();

        return view('users.index')->with([
            'users' => $users,
            'user_roles' => $user_roles,
            'edit' => false,
            'photo' => $this->photo
        ]);
    }
    public function usersDT() {
        $users = User::all();

        return DataTables::of($users)
            ->addColumn('email', function ($user) {
                return $user->email;
            })
            ->editColumn('role',function ($user) {
                return optional($user->role)->name;
            })
            ->editColumn('name',function ($user) {
                return $user->name;
            })
//            ->addColumn('phone',function ($user) {
//                return $user->mobile_no;
//            })
//            ->addColumn('actions', function($user) {
//                $actions = '<div class="pull-right">';
//                $actions .= '<a title="Edit User" class="btn btn-link btn-sm btn-warning btn-just-icon"><i class="material-icons">edit</i> </a>';
//                $actions .= '<a title="View User" class="btn btn-info btn-sm pull-right"><i class="material-icons">list</i> View</a>';
//                $actions .= '<a title="Manage User" class="btn btn-link btn-sm btn-info btn-just-icon"><i class="material-icons">dvr</i> </a>';
//                $actions .= '</div>';
//
//                return $actions;
//            })
//            ->rawColumns(['actions'])
            ->make(true);

    }

    public function register_user(Request $request)
    {
        $this->validate($request, [
            'user_role' => 'required|max:10',
            'email' => 'required|email|max:255|unique:users,email',
            'county_id' => 'required',
//            'sub_county_id' => 'required',
            'name' => 'required',
            'password' => 'required',
        ]);


        //$this->random_pass = $this->randomPassword();

        $user = new User();
        $user->name = $request->name;
        $user->user_group = $request->user_role;
        $user->email = $request->email;
        $user->county_id = $request->county_id;
        $user->sub_county_id = 1;//$request->sub_county_id;
        $user->partner = $request->partner;
        $user->password = bcrypt($request->password);
//        $user->password = bcrypt($this->random_pass);

        if ($user->saveOrFail()){
            //$user->notify(new UserCreated($this->random_pass));
            Session::flash("success", "User has been created");
        }

        return redirect('/users');
    }

    public function randomPassword()
    {
        $alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
        $pass = array();
        $alphaLength = strlen($alphabet) - 1;
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass);
    }

    public function editProfile() {
        $user = auth()->user();
        $photo = asset('assets/img/default-avatar.png');

        if ($user->photo) {
            $photo = Storage::disk('public')->url($user->photo);
        }



        return view('auth.edit-profile', [
            'user' => $user,
            'photo' => $photo
        ]);
    }

    public function updateProfile(Request $request) {
        $user = $request->user();
        request()->session()->flash('update_profile', true);

        $this->validate($request, [
            'first_name' => 'required',
            'surname' => 'required',
            'gender' => 'required',
            'dob' => 'required',
            'id_no' => 'required|unique:master_files,id_no,'.$user->masterfile->id,
            'email' => 'required|email|unique:users,email,'.$user->id,
            'mobile_no' => 'required|unique:users,mobile_no,' . $user->id
        ]);


        DB::transaction(function() use($request, $user) {


            if ($request->hasFile('photo')){

                $uploadedFile = $request->file('photo');
                $filename = time().$uploadedFile->getClientOriginalName();

                $request->file('photo')->storeAs("public/uploads", $filename);

                $user->photo = "uploads/".$filename;
            }

            $user->mobile_no = $request->mobile_no;
            $user->email = $request->email;
            $user->update();



            $masterfile = $user->masterfile;
            $masterfile->first_name = $request->first_name;
            $masterfile->middle_name = $request->middle_name;
            $masterfile->surname = $request->surname;
            $masterfile->gender = $request->gender;
            $masterfile->dob = $request->dob;
            $masterfile->id_no = $request->id_no;
            $masterfile->mobile_no2 = $request->mobile_no2;
            $masterfile->update();


            $this->_passed = true;
        });

        if ($this->_passed)
            request()->session()->flash('success', 'Users Profile has been updated.');
        else
            request()->session()->flash('warning', 'Failed to update profile!');

        return redirect('edit-profile');
    }


    public function myProfile() {
        $user = auth()->user();

        $photo = asset('assets/img/default-avatar.png');


        $profile_data = [
            'user' => $user,
            'photo' => $photo,
            'mfUser' => $user->masterfile,
        ];

        return view('auth.my-profile', $profile_data);
    }

    public function updatePassword(Request $request) {
        $request->session()->flash('update_password', true);

        $this->validate($request, [
            'current_password' => 'required',
            'password' => 'required|confirmed|min:6'
        ]);

        $check = auth()->validate(['email' => $request->user()->email, 'password' => request('current_password')]);

        if($check) {
            User::where('id', $request->user()->id)->update(['password' => bcrypt(request('password'))]);
            $request->session()->flash('success', 'You have reset your password.');
        } else {
            $request->session()->flash('warning', 'The current password is incorrect, please try again.');
        }

        return redirect('edit-profile');
    }

    public function user_groups()
    {
        $user_groups = UserGroup::all();

        return view('users.user_groups')->with([
            'user_groups' => $user_groups,
        ]);
    }

    public function new_user_group(Request $request)
    {
        $this->validate($request, [
            'group_name' => 'required|unique:user_groups,name',
        ]);


        $user_group = new UserGroup();
        $user_group->name = $request->group_name;
        $user_group->saveOrFail();


        Session::flash("success", "Group has been created");


        return redirect()->back();
    }


    public function get_group_details($id)
    {

        return UserGroup::find($id);
    }

    public function update_group_details(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|exists:user_groups,id',
            'group_name' => 'required|unique:user_groups,name',
        ]);


        $user_group = UserGroup::find($request->id);
        $user_group->name = $request->group_name;
        $user_group->update();


        Session::flash("success", "Group has been updated");


        return redirect()->back();
    }


    public function delete_group(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|exists:user_groups,id',
        ]);


        $user_group = UserGroup::find($request->id);
        $user_group->delete();


        Session::flash("success", "Group has been deleted");


        return redirect()->back();
    }

    public function user_group_details($_id)
    {
        $ug = UserGroup::find($_id);

        if(is_null($ug))
            abort(404);

        $users = User::where('user_group',$_id)->get();

        $user_permissions = UserPermission::where('group_id',$_id)->get();



        return view('users.group_details')->with([
            'group' => $ug,
            'users' => $users,
            'user_permissions' => $user_permissions
        ]);

    }
    public function userGroupDetailsDT($_id) {

        $users = User::where('user_group',$_id)->get();

        return DataTables::of($users)
            ->editColumn('id', function ($user) {
                return $user->id;
//                return '<a href="'.url('users/details/'.$user->id) .'" title="View User" >#'. $user->id .' </a>';
            })

            ->addColumn('role',function ($user) {
                return optional($user->role)->name;
            })
            ->editColumn('name',function ($user) {
                return $user->name;
            })
            ->editColumn('email',function ($user) {
                return $user->email;
            })
            ->addColumn('actions', function($user) {
                $actions = '<div class="pull-left">';
//                $actions .= '<a title="Edit User" class="btn btn-link btn-sm btn-warning btn-just-icon"><i class="material-icons">edit</i> </a>';
//                $actions .= '<a title="View User" href="'.url('users/details/'.$user->id) .'" class="btn btn-info btn-sm pull-right"><i class="material-icons">list</i> View</a>';
//                $actions .= '<a title="Manage User" class="btn btn-link btn-sm btn-info btn-just-icon"><i class="material-icons">dvr</i> </a>';
                $actions .= '</div>';

                return $actions;
            })
            ->rawColumns(['id','actions'])
            ->make(true);

    }
    public function add_group_permission(Request $request)
    {
        $this->validate($request, [
            'permission' => 'bail|required',
            'group_id' => 'bail|required',
        ]);

        foreach ($_POST['permission'] as $perm) {
            $userPermission = new userPermission();
            $userPermission->group_id = $request->group_id;
            $userPermission->permission_id = $perm;
            $userPermission->saveOrFail();
        }

        request()->session()->flash('success', 'Permissions added successfully');

        return redirect()->back();
    }
    public function delete_group_permission($group_id)
    {
        $userPermission = UserPermission::find($group_id);
        if ($data = $userPermission->delete()) {
            request()->session()->flash("success", "Permission deleted successfully.");
        }
        return redirect()->back();
    }







}
