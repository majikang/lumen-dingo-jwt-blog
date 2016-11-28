<?php

namespace App\Http\Controllers\Api\V1;

use App\Transformers\UserTransformer;
use App\Repositories\Contracts\UserRepositoryContract;
use Illuminate\Http\Request;

class UserController extends BaseController
{
    public function __construct(UserRepositoryContract $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    /**
     * @api {get} /users 用户列表(user list)
     * @apiDescription 用户列表(user list)
     * @apiGroup user
     * @apiPermission none
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": [
     *         {
     *           "id": 2,
     *           "email": "490554191@qq.com",
     *           "name": "fff",
     *           "created_at": "2015-11-12 10:37:14",
     *           "updated_at": "2015-11-13 02:26:36",
     *           "deleted_at": null
     *         }
     *       ],
     *       "meta": {
     *         "pagination": {
     *           "total": 1,
     *           "count": 1,
     *           "per_page": 15,
     *           "current_page": 1,
     *           "total_pages": 1,
     *           "links": []
     *         }
     *       }
     *     }
     */
    public function index(UserTransformer $userTransformer)
    {
        $users = $this->userRepository->paginate();

        return $this->response->paginator($users, $userTransformer);
    }

    /**
     * @api {put} /user/password 修改密码(edit password)
     * @apiDescription 修改密码(edit password)
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiParam {String} old_password          旧密码
     * @apiParam {String} password              新密码
     * @apiParam {String} password_confirmation 确认新密码
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 204 No Content
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "password": [
     *             "两次输入的密码不一致",
     *             "新旧密码不能相同"
     *         ],
     *         "password_confirmation": [
     *             "两次输入的密码不一致"
     *         ],
     *         "old_password": [
     *             "密码错误"
     *         ]
     *     }
     */
    public function editPassword(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'old_password' => 'required',
            'password' => 'required|confirmed|different:old_password',
            'password_confirmation' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }

        $user = $this->user();

        $auth = \Auth::once([
            'email' => $user->email,
            'password' => $request->get('old_password'),
        ]);

        if (! $auth) {
            return $this->response->errorUnauthorized();
        }

        $password = app('hash')->make($request->get('password'));
        $this->userRepository->update($user->id, ['password' => $password]);

        return $this->response->noContent();
    }

    /**
     * @api {get} /users/{id} 某个用户信息(some user's info)
     * @apiDescription 某个用户信息(some user's info)
     * @apiGroup user
     * @apiPermission none
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": {
     *         "id": 2,
     *         "email": "490554191@qq.com",
     *         "name": "fff",
     *         "created_at": "2015-11-12 10:37:14",
     *         "updated_at": "2015-11-13 02:26:36",
     *         "deleted_at": null
     *       }
     *     }
     */
    public function show($id)
    {
        $user = $this->userRepository->find($id);

        if (! $user) {
            return $this->response->errorNotFound();
        }

        return $this->response->item($user, new UserTransformer());
    }

    /**
     * @api {get} /user 当前用户信息(current user info)
     * @apiDescription 当前用户信息(current user info)
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": {
     *         "id": 2,
     *         "email": 'liyu01989@gmail.com',
     *         "name": "foobar",
     *         "created_at": "2015-09-08 09:13:57",
     *         "updated_at": "2015-09-08 09:13:57",
     *         "deleted_at": null
     *       }
     *     }
     */
    public function userShow()
    {
        return $this->response->item($this->user(), new UserTransformer());
    }

    /**
     * @api {patch} /user 修改个人信息(update my info)
     * @apiDescription 修改个人信息(update my info)
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiParam {String} [name] name
     * @apiParam {Url} [avatar] avatar
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *        "id": 2,
     *        "email": 'liyu01989@gmail.com',
     *        "name": "ffff",
     *        "created_at": "2015-10-28 07:30:56",
     *        "updated_at": "2015-10-28 09:42:43",
     *        "deleted_at": null,
     *     }
     */
    public function patch(Request $request)
    {
        $validator = \Validator::make($request->input(), [
            'name' => 'string|max:50',
            'avatar' => 'url',
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }

        $user = $this->user();
        $attributes = array_filter($request->only('name', 'avatar'));

        if ($attributes) {
            $user = $this->userRepository->update($user->id, $attributes);
        }

        return $this->response->item($user, new UserTransformer());
    }

    public function store(Request $request)
    {
        $validator = \Validator::make($request->input(), [
            'email' => 'required|email|unique:users',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator->messages());
        }

        $email = $request->get('email');
        $password = $request->get('password');

        $attributes = [
            'email' => $email,
            'password' => app('hash')->make($password),
        ];

        $user = $this->userRepository->create($attributes);

        // 用户注册事件
        $token = $this->auth->fromUser($user);

        return $this->response->array(compact('token'));
    }
}
