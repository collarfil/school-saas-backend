<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    /**
     * Determine whether the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return $this->isTicketUser($user);
    }

    /**
     * Determine whether the user can view a ticket.
     */
    public function view(User $user, SupportTicket $ticket): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | School Admin / Support Staff
        |--------------------------------------------------------------------------
        */

        return $user->school_id === $ticket->school_id;
    }

    /**
     * Determine whether the user can create tickets.
     */
    public function create(User $user): bool
    {
        return $this->hasRole($user, 'school_admin');
    }

    /**
     * Determine whether the user can update a ticket.
     */
    public function update(User $user, SupportTicket $ticket): bool
    {
        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        return $this->hasRole($user, 'school_admin')
            && $user->school_id === $ticket->school_id;
    }

    /**
     * Determine whether the user can reply.
     */
    public function reply(User $user, SupportTicket $ticket): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        /*
        |--------------------------------------------------------------------------
        | Support Staff
        |--------------------------------------------------------------------------
        */

        if ($this->hasRole($user, 'support_staff')) {
            return $user->school_id === $ticket->school_id
                || $ticket->assigned_to === $user->id;
        }

        /*
        |--------------------------------------------------------------------------
        | School Admin
        |--------------------------------------------------------------------------
        */

        return $this->hasRole($user, 'school_admin')
            && $user->school_id === $ticket->school_id
            && !$ticket->isClosed();
    }

    /**
     * Determine whether the user can change status.
     */
    public function changeStatus(
        User $user,
        SupportTicket $ticket
    ): bool {
        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        if (
            $this->hasRole($user, 'support_staff') &&
            $ticket->assigned_to === $user->id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign a ticket.
     */
    public function assign(
        User $user,
        SupportTicket $ticket
    ): bool {
        return $this->hasRole($user, 'super_admin');
    }

    /**
     * Determine whether the user can resolve a ticket.
     */
    public function resolve(
        User $user,
        SupportTicket $ticket
    ): bool {
        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        if ($this->hasRole($user, 'support_staff')) {
            return $ticket->assigned_to === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can close a ticket.
     */
    public function close(
        User $user,
        SupportTicket $ticket
    ): bool {
        if ($this->hasRole($user, 'super_admin')) {
            return true;
        }

        return $this->hasRole($user, 'school_admin')
            && $user->school_id === $ticket->school_id
            && $ticket->isResolved();
    }

    /**
     * Determine whether the user can delete a ticket.
     */
    public function delete(
        User $user,
        SupportTicket $ticket
    ): bool {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function isTicketUser(User $user): bool
    {
        return $this->hasRole($user, 'school_admin')
            || $this->hasRole($user, 'super_admin')
            || $this->hasRole($user, 'support_staff');
    }

    private function hasRole(User $user, string $role): bool
    {
        /*
        |--------------------------------------------------------------------------
        | Existing role column
        |--------------------------------------------------------------------------
        */

        if (!empty($user->role)) {
            return strtolower($user->role) === $role;
        }

        /*
        |--------------------------------------------------------------------------
        | Spatie Permission support
        |--------------------------------------------------------------------------
        */

        if (method_exists($user, 'hasRole')) {
            return $user->hasRole($role);
        }

        return false;
    }
}