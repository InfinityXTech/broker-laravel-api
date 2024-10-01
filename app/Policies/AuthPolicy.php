<?php
namespace App\Policies;
 
use App\Post;
use Illuminate\Foundation\Auth\User;
use Illuminate\Auth\Access\HandlesAuthorization;
 
class AuthPolicy
{
  use HandlesAuthorization;
 
  /**
   * Determine whether the user can view the post.
   *
   * @param  \App\User  $user
   * @return mixed
   */
  public function login(User $user)
  {
    return TRUE;
  }
 
  /**
   * Determine whether the user can create posts.
   *
   * @param  \App\User  $user
   * @return mixed
   */
  public function google(User $user)
  {
    return TRUE;
  }
 
}