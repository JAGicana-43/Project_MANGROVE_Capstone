@extends('layouts.admin')

@section('title', 'User Management')

@section('content')
@php
    $usersPayload = $users->map(function ($u) {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'avatar_url' => $u->avatar_url,
            'email' => $u->email,
            'role' => optional($u->role)->name ?? 'unknown',
            'status' => $u->status,
            'is_active' => (bool) $u->is_active,
            'created_at' => $u->created_at?->format('M d, Y'),
        ];
    })->values();
@endphp

<div
    x-data="userManagement(@js($usersPayload))"
    x-init="init()"
>
    <div class="page-header">
        <div>
            <h1>User Management</h1>
            <p>Create and manage user accounts. Only administrators can add new accounts.</p>
        </div>
        <button type="button" class="btn btn-primary" @click="openCreateModal()">
            <i class="fa-solid fa-user-plus"></i> Create Account
        </button>
    </div>

    <div class="stat-grid">
        <div class="glass-panel stat-tile">
            <span class="stat-tile-icon"><i class="fa-solid fa-users"></i></span>
            <div>
                <div class="stat-tile-value" data-count="{{ $stats['total'] }}">0</div>
                <div class="stat-tile-label">Total Accounts</div>
            </div>
        </div>
        <div class="glass-panel stat-tile {{ $stats['pending'] > 0 ? 'warn' : '' }}">
            <span class="stat-tile-icon"><i class="fa-solid fa-hourglass-half"></i></span>
            <div>
                <div class="stat-tile-value" data-count="{{ $stats['pending'] }}">0</div>
                <div class="stat-tile-label">Pending</div>
            </div>
        </div>
        <div class="glass-panel stat-tile">
            <span class="stat-tile-icon"><i class="fa-solid fa-user-check"></i></span>
            <div>
                <div class="stat-tile-value" data-count="{{ $stats['approved'] }}">0</div>
                <div class="stat-tile-label">Approved</div>
            </div>
        </div>
        <div class="glass-panel stat-tile danger">
            <span class="stat-tile-icon"><i class="fa-solid fa-user-xmark"></i></span>
            <div>
                <div class="stat-tile-value" data-count="{{ $stats['rejected'] }}">0</div>
                <div class="stat-tile-label">Rejected</div>
            </div>
        </div>
    </div>

    <div class="glass-panel toolbar-card">
        <div class="search-field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" placeholder="Search by name or email" x-model="search">
        </div>
        <select x-model="roleFilter">
            <option value="all">All roles</option>
            <option value="admin">Admin</option>
            <option value="researcher">Researcher</option>
            <option value="field_technician">Field Researcher</option>
        </select>
        <select x-model="statusFilter">
            <option value="all">All statuses</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>
        <span class="result-count" x-text="filteredUsers.length + ' of ' + users.length"></span>
    </div>

    <div class="bulk-bar" x-show="selected.length > 0" x-cloak x-transition>
        <span><strong x-text="selected.length"></strong> selected</span>
        <div class="bulk-actions">
            <button type="button" class="btn btn-primary btn-sm" :disabled="bulkBusy" @click="bulkAct('approve')"><i class="fa-solid fa-check-double"></i> Approve Selected</button>
            <button type="button" class="btn btn-danger btn-sm" :disabled="bulkBusy" @click="bulkAct('reject')"><i class="fa-solid fa-ban"></i> Reject Selected</button>
            <button type="button" class="btn btn-ghost btn-sm" @click="selected = []">Clear</button>
        </div>
    </div>

    <div class="glass-panel table-card">
        <table>
            <thead>
                <tr>
                    <th class="col-check"><input type="checkbox" :checked="allVisibleSelected" @change="toggleSelectAll()"></th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Access</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="u in filteredUsers" :key="u.id">
                    <tr :class="{ 'is-pending': u.status === 'pending', 'is-self': u.id === currentUserId }">
                        <td class="col-check">
                            <input type="checkbox" :checked="selected.includes(u.id)" @change="toggleSelect(u.id)" x-show="u.id !== currentUserId">
                        </td>
                        <td>
                            <a href="#" class="user-link" @click.prevent="openDetail(u.id)">
                                <img :src="u.avatar_url" class="avatar-circle" x-show="u.avatar_url" alt="">
                                <span class="avatar-circle avatar-fallback" x-show="!u.avatar_url" x-text="initials(u.name)"></span>
                                <div>
                                    <div class="user-name" x-text="u.name"></div>
                                    <div class="user-email" x-text="u.email"></div>
                                </div>
                            </a>
                        </td>
                        <td><span class="badge badge-role" x-text="roleLabel(u.role)"></span></td>
                        <td>
                            <span class="badge badge-status" :class="u.status">
                                <span class="badge-dot"></span>
                                <span x-text="u.status"></span>
                            </span>
                        </td>
                        <td>
                            <span class="badge" :class="u.is_active ? 'badge-active' : 'badge-inactive'" x-text="u.is_active ? 'Active' : 'Inactive'"></span>
                        </td>
                        <td x-text="u.created_at"></td>
                        <td>
                            <div class="row-actions" x-show="u.id !== currentUserId">
                                <template x-if="u.status === 'pending'">
                                    <span style="display:flex; gap:6px;">
                                        <button type="button" class="action-btn action-approve" :disabled="busy.includes(u.id)" @click="rowAct(u.id, 'approve')"><i class="fa-solid fa-check"></i> Approve</button>
                                        <button type="button" class="action-btn action-reject" :disabled="busy.includes(u.id)" @click="rowAct(u.id, 'reject')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                    </span>
                                </template>
                                <template x-if="u.status !== 'pending'">
                                    <span>
                                        <button type="button" class="action-btn action-deactivate" x-show="u.is_active" :disabled="busy.includes(u.id)" @click="rowAct(u.id, 'deactivate')"><i class="fa-solid fa-ban"></i> Deactivate</button>
                                        <button type="button" class="action-btn action-activate" x-show="!u.is_active" :disabled="busy.includes(u.id)" @click="rowAct(u.id, 'activate')"><i class="fa-solid fa-check"></i> Activate</button>
                                    </span>
                                </template>
                            </div>
                            <span class="user-email" x-show="u.id === currentUserId">This is you</span>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
        <div class="empty-note" x-show="filteredUsers.length === 0" x-cloak>No users match your filters.</div>
    </div>

    <!-- Detail Drawer -->
    <div class="drawer-backdrop" x-show="drawerOpen" x-cloak x-transition.opacity @click="closeDetail()"></div>
    <aside class="glass-panel drawer" x-show="drawerOpen" x-cloak x-transition:enter="drawer-enter" x-transition:enter-start="drawer-enter-start" x-transition:enter-end="drawer-enter-end">
        <template x-if="drawerData">
            <div>
                <div class="drawer-header">
                    <div class="drawer-identity">
                        <img :src="drawerData.avatar_url" class="drawer-avatar" x-show="drawerData.avatar_url" alt="">
                        <span class="drawer-avatar avatar-fallback" x-show="!drawerData.avatar_url" x-text="initials(drawerData.name)"></span>
                        <div>
                            <div class="drawer-title" x-text="drawerData.name"></div>
                            <div class="drawer-sub" x-text="drawerData.email"></div>
                        </div>
                    </div>
                    <div style="display:flex; gap:6px; flex-shrink:0;">
                        <button type="button" class="icon-btn" @click="openEditModal()" title="Edit name or photo"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="icon-btn" @click="closeDetail()"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                </div>

                <div class="drawer-badges">
                    <span class="badge badge-role" x-text="roleLabel(drawerData.role)"></span>
                    <span class="badge badge-status" :class="drawerData.status" x-text="drawerData.status"></span>
                    <span class="badge" :class="drawerData.is_active ? 'badge-active' : 'badge-inactive'" x-text="drawerData.is_active ? 'Active' : 'Inactive'"></span>
                </div>

                <dl class="drawer-facts">
                    <div><dt>Joined</dt><dd x-text="drawerData.created_at"></dd></div>
                    <div><dt>Last login</dt><dd x-text="drawerData.last_login_at"></dd></div>
                    <div x-show="drawerData.approved_by"><dt>Reviewed by</dt><dd x-text="drawerData.approved_by"></dd></div>
                    <div x-show="drawerData.approved_at"><dt>Reviewed on</dt><dd x-text="drawerData.approved_at"></dd></div>
                </dl>

                <template x-if="drawerData.projects && drawerData.projects.length > 0">
                    <div class="drawer-section">
                        <h3>Project Memberships</h3>
                        <ul class="drawer-list">
                            <template x-for="p in drawerData.projects">
                                <li><i class="fa-solid fa-folder"></i> <span x-text="p.name"></span> <span class="drawer-list-tag" x-text="p.role"></span></li>
                            </template>
                        </ul>
                    </div>
                </template>

                <div class="drawer-section">
                    <h3>Recent Activity</h3>
                    <template x-if="drawerData.activity.length === 0">
                        <p class="dash-empty" style="padding: 12px 0;">No recorded activity.</p>
                    </template>
                    <ul class="drawer-list">
                        <template x-for="a in drawerData.activity">
                            <li>
                                <i class="fa-solid fa-circle-dot"></i>
                                <span x-text="(a.note || a.action).replace(/_/g, ' ')"></span>
                                <span class="drawer-list-time" x-text="a.logged_at"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </template>
        <div x-show="drawerLoading" class="dash-empty"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</div>
    </aside>

    <!-- Create Account Modal -->
    <div class="drawer-backdrop" x-show="createModalOpen" x-cloak x-transition.opacity @click="closeCreateModal()"></div>
    <div class="glass-panel create-modal" x-show="createModalOpen" x-cloak x-transition>
        <div class="drawer-header">
            <div>
                <div class="drawer-title">Create Account</div>
                <div class="drawer-sub">New accounts are approved and active immediately.</div>
            </div>
            <button type="button" class="icon-btn" @click="closeCreateModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form @submit.prevent="submitCreate()" class="create-form" data-no-page-transition>
            <div class="edit-avatar-row">
                <img :src="createPreviewUrl" class="drawer-avatar avatar-lg" x-show="createPreviewUrl" alt="">
                <span class="drawer-avatar avatar-lg avatar-fallback" x-show="!createPreviewUrl" x-text="initials(createFullName())"></span>
                <label class="btn btn-ghost btn-sm edit-photo-btn">
                    <i class="fa-solid fa-camera"></i> Add Photo
                    <input type="file" accept="image/png,image/jpeg,image/webp" @change="onCreateAvatarSelected($event)">
                </label>
            </div>

            <div class="name-field-row">
                <label>First Name
                    <input type="text" x-model="createForm.first_name" required>
                </label>
                <label>Middle Name
                    <input type="text" x-model="createForm.middle_name">
                </label>
                <label>Last Name
                    <input type="text" x-model="createForm.last_name" required>
                </label>
            </div>
            <label>Email Address
                <input type="email" x-model="createForm.email" required>
            </label>
            <label>Role
                <select x-model="createForm.role" required>
                    <option value="researcher">Researcher</option>
                    <option value="field_technician">Field Researcher</option>
                    <option value="admin">Administrator</option>
                </select>
            </label>
            <label>Password
                <input type="password" x-model="createForm.password" id="createPassword" minlength="8" required>
            </label>
            <div class="password-strength" id="createPasswordStrength" hidden>
                <div class="password-strength-bar">
                    <div class="password-strength-fill" id="createPasswordStrengthFill"></div>
                </div>
                <span class="password-strength-label" id="createPasswordStrengthLabel"></span>
            </div>
            <ul class="password-requirements" id="createPasswordRequirements">
                <li data-rule="length"><i class="fa-solid fa-circle"></i> At least 8 characters</li>
                <li data-rule="case"><i class="fa-solid fa-circle"></i> Upper and lowercase letters</li>
                <li data-rule="number"><i class="fa-solid fa-circle"></i> At least one number</li>
                <li data-rule="symbol"><i class="fa-solid fa-circle"></i> At least one symbol (optional, adds strength)</li>
            </ul>
            <label>Confirm Password
                <input type="password" x-model="createForm.password_confirmation" minlength="8" required>
            </label>

            <p class="form-error" x-show="createError" x-text="createError" x-cloak></p>

            <div class="create-form-actions">
                <button type="button" class="btn btn-ghost" @click="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" :disabled="createBusy">
                    <i class="fa-solid" :class="createBusy ? 'fa-spinner fa-spin' : 'fa-user-plus'"></i>
                    <span x-text="createBusy ? 'Creating...' : 'Create Account'"></span>
                </button>
            </div>
        </form>
    </div>

    <!-- Edit Account Modal -->
    <div class="drawer-backdrop" x-show="editModalOpen" x-cloak x-transition.opacity @click="closeEditModal()"></div>
    <div class="glass-panel create-modal modal-lg" x-show="editModalOpen" x-cloak x-transition>
        <div class="drawer-header">
            <div>
                <div class="drawer-title">Edit Account</div>
                <div class="drawer-sub">Update this user's name or profile photo.</div>
            </div>
            <button type="button" class="icon-btn" @click="closeEditModal()"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <form @submit.prevent="submitEdit()" class="create-form" data-no-page-transition>
            <div class="edit-avatar-row">
                <img :src="editPreviewUrl || editForm.avatar_url" class="drawer-avatar avatar-lg" x-show="editPreviewUrl || editForm.avatar_url" alt="">
                <span class="drawer-avatar avatar-lg avatar-fallback" x-show="!editPreviewUrl && !editForm.avatar_url" x-text="initials(editForm.name)"></span>
                <div class="edit-avatar-actions">
                    <label class="btn btn-ghost btn-sm edit-photo-btn">
                        <i class="fa-solid fa-camera"></i> Change Photo
                        <input type="file" accept="image/png,image/jpeg,image/webp" @change="onAvatarSelected($event)">
                    </label>
                    <button type="button" class="btn btn-ghost btn-sm" x-show="editPreviewUrl || editForm.avatar_url" @click="removeEditPhoto()">
                        <i class="fa-solid fa-trash"></i> Remove Photo
                    </button>
                </div>
            </div>

            <label>Full Name
                <input type="text" x-model="editForm.name" required>
            </label>

            <p class="form-error" x-show="editError" x-text="editError" x-cloak></p>

            <div class="create-form-actions">
                <button type="button" class="btn btn-ghost" @click="closeEditModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" :disabled="editBusy">
                    <i class="fa-solid" :class="editBusy ? 'fa-spinner fa-spin' : 'fa-check'"></i>
                    <span x-text="editBusy ? 'Saving...' : 'Save Changes'"></span>
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<style>
    .toolbar-card {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        margin-bottom: 14px;
        flex-wrap: wrap;
    }
    .search-field {
        flex: 1 1 220px;
        min-width: 180px;
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 0 12px;
        height: 40px;
        border-radius: 10px;
        background: rgba(var(--surface-rgb),0.7);
        border: 1px solid var(--border-soft);
    }
    .search-field i { color: rgba(var(--ink-rgb),0.4); font-size: 13px; }
    .search-field input { border: none; outline: none; background: transparent; font: inherit; font-size: 13.5px; width: 100%; color: var(--dark-gray); }
    .toolbar-card select {
        height: 40px;
        padding: 0 10px;
        border-radius: 10px;
        border: 1px solid var(--border-soft);
        background: rgba(var(--surface-rgb),0.7);
        font: inherit;
        font-size: 13px;
        font-weight: 600;
        color: var(--dark-gray);
    }
    .result-count { margin-left: auto; font-size: 12.5px; font-weight: 700; color: rgba(var(--ink-rgb),0.5); white-space: nowrap; }

    .bulk-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 16px;
        margin-bottom: 14px;
        border-radius: 12px;
        background: rgba(45,106,79,0.10);
        border: 1px solid rgba(45,106,79,0.18);
        font-size: 13.5px;
        font-weight: 700;
    }
    .bulk-actions { display: flex; gap: 8px; }

    .table-card { padding: 0; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        text-align: left;
        font-size: 11.5px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: rgba(var(--ink-rgb),0.55);
        padding: 12px 14px;
        border-bottom: 1px solid var(--border-soft);
        background: rgba(var(--surface-rgb),0.5);
    }
    tbody td { padding: 12px 14px; border-bottom: 1px solid var(--border-soft); font-size: 13.5px; vertical-align: middle; }
    tbody tr:last-child td { border-bottom: none; }
    tbody tr.is-pending { background: rgba(240,165,0,0.05); }
    .col-check { width: 40px; }

    .user-link { display: flex; align-items: center; gap: 10px; }
    .user-name { font-weight: 700; }
    .user-email { color: rgba(var(--ink-rgb),0.55); font-size: 12px; }

    .avatar-circle {
        width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0;
        object-fit: cover;
    }
    .avatar-fallback {
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--sage-green, #74C69D); color: #1B4332;
        font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 12px;
    }

    .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 11.5px; font-weight: 800; text-transform: capitalize; white-space: nowrap; }
    .badge-role { background: rgba(45,106,79,0.12); color: var(--accent-green); }
    .badge-status.pending { background: rgba(240,165,0,0.14); color: #9a5b00; }
    .badge-status.approved { background: rgba(45,106,79,0.12); color: var(--accent-green); }
    .badge-status.rejected { background: var(--danger-soft); color: var(--danger); }
    .badge-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
    .badge-active { background: rgba(45,106,79,0.12); color: var(--accent-green); }
    .badge-inactive { background: rgba(var(--ink-rgb),0.08); color: rgba(var(--ink-rgb),0.55); }

    .row-actions { display: flex; flex-wrap: wrap; gap: 6px; }
    .action-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 10px; border-radius: 8px; border: 1px solid var(--border-soft);
        background: var(--surface); color: var(--dark-gray); font: inherit; font-size: 12px; font-weight: 700; cursor: pointer;
        transition: transform 0.15s ease;
    }
    .action-btn:hover { transform: translateY(-1px); }
    .action-btn:disabled { opacity: 0.5; cursor: wait; transform: none; }
    .action-approve { background: var(--forest-green); border-color: var(--forest-green); color: #fff; }
    .action-reject { background: var(--danger-soft); border-color: #FFD2CC; color: var(--danger); }
    .action-deactivate { background: rgba(var(--ink-rgb),0.06); color: var(--dark-gray); }
    .action-activate { background: rgba(45,106,79,0.10); border-color: rgba(45,106,79,0.2); color: var(--accent-green); }

    .empty-note { padding: 40px; text-align: center; color: rgba(var(--ink-rgb),0.5); font-weight: 600; }

    /* Drawer */
    .drawer-backdrop { position: fixed; inset: 0; z-index: 1400; background: rgba(20,25,22,0.32); backdrop-filter: blur(2px); }
    .drawer {
        position: fixed;
        top: 12px;
        right: 12px;
        bottom: 12px;
        z-index: 1500;
        width: min(400px, calc(100vw - 24px));
        padding: 22px;
        overflow-y: auto;
    }
    .drawer-enter { transition: transform 0.24s cubic-bezier(0.22,1,0.36,1), opacity 0.24s ease; }
    .drawer-enter-start { transform: translateX(24px); opacity: 0; }
    .drawer-enter-end { transform: translateX(0); opacity: 1; }

    .drawer-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 14px; }
    .drawer-identity { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .drawer-title { font-family: 'Poppins', sans-serif; font-weight: 800; font-size: 17px; }
    .drawer-sub { color: rgba(var(--ink-rgb),0.55); font-size: 12.5px; margin-top: 2px; }

    .drawer-avatar {
        width: 48px; height: 48px; border-radius: 50%; flex-shrink: 0;
        object-fit: cover; font-size: 16px;
    }

    .avatar-lg { width: 88px; height: 88px; font-size: 28px; }

    .edit-avatar-row { display: flex; align-items: center; gap: 18px; margin-bottom: 4px; }
    .edit-avatar-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
    .edit-photo-btn { position: relative; overflow: hidden; cursor: pointer; }
    .edit-photo-btn input[type="file"] {
        position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .drawer-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }

    .drawer-facts { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px; }
    .drawer-facts dt { font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.03em; color: rgba(var(--ink-rgb),0.5); font-weight: 800; }
    .drawer-facts dd { font-size: 13px; font-weight: 700; margin-top: 2px; }

    .drawer-section { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--border-soft); }
    .drawer-section h3 { font-size: 12.5px; text-transform: uppercase; letter-spacing: 0.03em; color: rgba(var(--ink-rgb),0.55); margin-bottom: 10px; }
    .drawer-list { list-style: none; display: flex; flex-direction: column; gap: 9px; }
    .drawer-list li { display: flex; align-items: center; gap: 8px; font-size: 12.5px; }
    .drawer-list i { color: var(--accent-green); font-size: 8px; }
    .drawer-list-tag { margin-left: auto; color: rgba(var(--ink-rgb),0.45); text-transform: capitalize; }
    .drawer-list-time { margin-left: auto; color: rgba(var(--ink-rgb),0.45); font-size: 11px; white-space: nowrap; }

    /* Create Account Modal */
    .create-modal {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1500;
        width: min(420px, calc(100vw - 32px));
        max-height: calc(100vh - 48px);
        overflow-y: auto;
        padding: 22px;
    }
    .create-modal.modal-lg { width: min(520px, calc(100vw - 32px)); padding: 28px; }
    .create-form { display: flex; flex-direction: column; gap: 13px; margin-top: 6px; }
    .create-form label { display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; font-weight: 800; color: rgba(var(--ink-rgb),0.7); }
    .create-form input, .create-form select {
        height: 42px; padding: 0 12px; border-radius: 10px; border: 1px solid var(--border-soft);
        background: rgba(var(--surface-rgb),0.85); font: inherit; font-size: 13.5px; font-weight: 600; color: var(--dark-gray);
    }
    .create-form input:focus, .create-form select:focus { outline: 2px solid rgba(116,198,157,0.45); border-color: var(--accent-green); }
    .create-form-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px; }
    .form-error { color: var(--danger); font-size: 12.5px; font-weight: 700; background: var(--danger-soft); padding: 8px 10px; border-radius: 8px; }

    .name-field-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; min-width: 0; }
    .name-field-row label { min-width: 0; }
    .name-field-row input { width: 100%; min-width: 0; }

    @media (max-width: 480px) {
        .name-field-row { grid-template-columns: 1fr; }
    }

    .password-strength { display: flex; align-items: center; gap: 10px; margin-top: -6px; }
    .password-strength-bar { flex: 1; height: 6px; border-radius: 999px; background: rgba(var(--ink-rgb),0.12); overflow: hidden; }
    .password-strength-fill { height: 100%; width: 0%; border-radius: 999px; background: #C0392B; transition: width 0.2s ease, background 0.2s ease; }
    .password-strength-label { font-size: 12px; font-weight: 700; color: rgba(var(--ink-rgb),0.6); white-space: nowrap; }

    .password-requirements { list-style: none; display: flex; flex-direction: column; gap: 4px; margin-top: -6px; font-size: 12px; color: rgba(var(--ink-rgb),0.6); font-weight: 600; }
    .password-requirements li { display: flex; align-items: center; gap: 8px; }
    .password-requirements li i { font-size: 6px; color: rgba(var(--ink-rgb),0.3); }
    .password-requirements li.met { color: var(--forest-green); }
    .password-requirements li.met i { font-size: 12px; color: var(--forest-green); }
    .password-requirements li.met i::before { content: "\f00c"; }
</style>
<script>
    function userManagement(initialUsers) {
        return {
            users: initialUsers,
            currentUserId: @json($currentUser->id),
            search: '',
            roleFilter: 'all',
            statusFilter: 'all',
            selected: [],
            busy: [],
            bulkBusy: false,
            drawerOpen: false,
            drawerLoading: false,
            drawerData: null,
            createModalOpen: false,
            createBusy: false,
            createError: null,
            createForm: { first_name: '', middle_name: '', last_name: '', email: '', role: 'researcher', password: '', password_confirmation: '' },
            createAvatarFile: null,
            createPreviewUrl: null,
            editModalOpen: false,
            editBusy: false,
            editError: null,
            editForm: { id: null, name: '', avatar_url: null },
            editAvatarFile: null,
            editPreviewUrl: null,
            editRemovePhoto: false,

            init() {},

            initials(name) {
                if (!name) return 'U';
                return name.trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0).toUpperCase()).join('');
            },

            // The underlying role slug stays 'field_technician' (DB value,
            // auth checks, route names all key off it) -- this is purely a
            // display translation so the users table/drawer shows the
            // current user-facing term instead of the raw slug.
            roleLabel(role) {
                if (role === 'field_technician') return 'field researcher';
                return (role || 'unknown').replace('_', ' ');
            },

            createFullName() {
                return [this.createForm.first_name, this.createForm.middle_name, this.createForm.last_name]
                    .map((part) => (part || '').trim())
                    .filter(Boolean)
                    .join(' ');
            },

            openEditModal() {
                if (!this.drawerData) return;
                this.editForm = { id: this.drawerData.id, name: this.drawerData.name, avatar_url: this.drawerData.avatar_url };
                this.editAvatarFile = null;
                this.editPreviewUrl = null;
                this.editRemovePhoto = false;
                this.editError = null;
                this.editModalOpen = true;
            },

            closeEditModal() {
                this.editModalOpen = false;
                if (this.editPreviewUrl) URL.revokeObjectURL(this.editPreviewUrl);
                this.editPreviewUrl = null;
                this.editAvatarFile = null;
                this.editRemovePhoto = false;
            },

            onAvatarSelected(event) {
                var file = event.target.files[0];
                if (!file) return;
                if (this.editPreviewUrl) URL.revokeObjectURL(this.editPreviewUrl);
                this.editAvatarFile = file;
                this.editPreviewUrl = URL.createObjectURL(file);
                this.editRemovePhoto = false;
            },

            removeEditPhoto() {
                if (this.editPreviewUrl) URL.revokeObjectURL(this.editPreviewUrl);
                this.editAvatarFile = null;
                this.editPreviewUrl = null;
                this.editForm.avatar_url = null;
                this.editRemovePhoto = true;
            },

            submitEdit() {
                if (this.editBusy) return;
                this.editError = null;
                this.editBusy = true;

                var formData = new FormData();
                formData.append('name', this.editForm.name);
                if (this.editAvatarFile) {
                    formData.append('avatar', this.editAvatarFile);
                } else if (this.editRemovePhoto) {
                    formData.append('remove_avatar', '1');
                }

                fetch('/admin/users/' + this.editForm.id, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.adminCsrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                    .then((response) => response.json().then((data) => ({ ok: response.ok, data: data })))
                    .then((result) => {
                        if (!result.ok) {
                            var firstError = result.data.errors ? Object.values(result.data.errors)[0][0] : (result.data.message || 'Could not update account.');
                            throw new Error(firstError);
                        }
                        var user = this.findUser(result.data.user.id);
                        if (user) {
                            user.name = result.data.user.name;
                            user.avatar_url = result.data.user.avatar_url;
                        }
                        if (this.drawerData && this.drawerData.id === result.data.user.id) {
                            this.drawerData.name = result.data.user.name;
                            this.drawerData.avatar_url = result.data.user.avatar_url;
                        }
                        window.adminToast(result.data.message, 'success');
                        this.closeEditModal();
                    })
                    .catch((error) => { this.editError = error.message || 'Could not update account.'; })
                    .finally(() => { this.editBusy = false; });
            },

            openCreateModal() {
                this.createForm = { first_name: '', middle_name: '', last_name: '', email: '', role: 'researcher', password: '', password_confirmation: '' };
                this.createAvatarFile = null;
                if (this.createPreviewUrl) URL.revokeObjectURL(this.createPreviewUrl);
                this.createPreviewUrl = null;
                this.createError = null;
                this.createModalOpen = true;
                this.$nextTick(() => resetCreatePasswordStrength());
            },

            closeCreateModal() {
                this.createModalOpen = false;
                if (this.createPreviewUrl) URL.revokeObjectURL(this.createPreviewUrl);
                this.createPreviewUrl = null;
                this.createAvatarFile = null;
            },

            onCreateAvatarSelected(event) {
                var file = event.target.files[0];
                if (!file) return;
                if (this.createPreviewUrl) URL.revokeObjectURL(this.createPreviewUrl);
                this.createAvatarFile = file;
                this.createPreviewUrl = URL.createObjectURL(file);
            },

            submitCreate() {
                if (this.createBusy) return;
                this.createError = null;

                if (this.createForm.password !== this.createForm.password_confirmation) {
                    this.createError = 'Passwords do not match.';
                    return;
                }

                this.createBusy = true;

                var formData = new FormData();
                formData.append('first_name', this.createForm.first_name);
                formData.append('middle_name', this.createForm.middle_name);
                formData.append('last_name', this.createForm.last_name);
                formData.append('email', this.createForm.email);
                formData.append('role', this.createForm.role);
                formData.append('password', this.createForm.password);
                formData.append('password_confirmation', this.createForm.password_confirmation);
                if (this.createAvatarFile) formData.append('avatar', this.createAvatarFile);

                fetch('/admin/users', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.adminCsrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                    .then((response) => response.json().then((data) => ({ ok: response.ok, data: data })))
                    .then((result) => {
                        if (!result.ok) {
                            var firstError = result.data.errors ? Object.values(result.data.errors)[0][0] : (result.data.message || 'Could not create account.');
                            throw new Error(firstError);
                        }
                        this.users.unshift({
                            id: result.data.user.id,
                            name: result.data.user.name,
                            avatar_url: result.data.user.avatar_url,
                            email: result.data.user.email,
                            role: result.data.user.role,
                            status: result.data.user.status,
                            is_active: result.data.user.is_active,
                            created_at: result.data.user.created_at
                        });
                        window.adminToast(result.data.message, 'success');
                        this.createModalOpen = false;
                    })
                    .catch((error) => { this.createError = error.message || 'Could not create account.'; })
                    .finally(() => { this.createBusy = false; });
            },

            get filteredUsers() {
                var term = this.search.trim().toLowerCase();
                return this.users.filter((u) => {
                    if (this.roleFilter !== 'all' && u.role !== this.roleFilter) return false;
                    if (this.statusFilter !== 'all' && u.status !== this.statusFilter) return false;
                    if (term && !(u.name.toLowerCase().includes(term) || u.email.toLowerCase().includes(term))) return false;
                    return true;
                });
            },

            get allVisibleSelected() {
                var selectable = this.filteredUsers.filter((u) => u.id !== this.currentUserId);
                return selectable.length > 0 && selectable.every((u) => this.selected.includes(u.id));
            },

            toggleSelect(id) {
                var index = this.selected.indexOf(id);
                if (index === -1) this.selected.push(id);
                else this.selected.splice(index, 1);
            },

            toggleSelectAll() {
                var selectable = this.filteredUsers.filter((u) => u.id !== this.currentUserId).map((u) => u.id);
                if (this.allVisibleSelected) {
                    this.selected = this.selected.filter((id) => !selectable.includes(id));
                } else {
                    selectable.forEach((id) => { if (!this.selected.includes(id)) this.selected.push(id); });
                }
            },

            findUser(id) {
                return this.users.find((u) => u.id === id);
            },

            rowAct(id, action) {
                if (this.busy.includes(id)) return;
                this.busy.push(id);

                fetch('/admin/users/' + id + '/' + action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.adminCsrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then((response) => response.json().then((data) => ({ ok: response.ok, data: data })))
                    .then((result) => {
                        if (!result.ok) throw new Error(result.data.message || 'Action failed.');
                        var user = this.findUser(id);
                        if (user && result.data.user) {
                            user.status = result.data.user.status;
                            user.is_active = result.data.user.is_active;
                        }
                        window.adminToast(result.data.message, 'success');
                    })
                    .catch((error) => window.adminToast(error.message || 'Something went wrong.', 'error'))
                    .finally(() => { this.busy = this.busy.filter((busyId) => busyId !== id); });
            },

            bulkAct(action) {
                if (this.bulkBusy || this.selected.length === 0) return;
                this.bulkBusy = true;
                var ids = this.selected.slice();

                fetch('/admin/users/bulk-' + action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.adminCsrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ user_ids: ids })
                })
                    .then((response) => response.json().then((data) => ({ ok: response.ok, data: data })))
                    .then((result) => {
                        if (!result.ok) throw new Error(result.data.message || 'Bulk action failed.');
                        var newStatus = action === 'approve' ? 'approved' : 'rejected';
                        ids.forEach((id) => {
                            var user = this.findUser(id);
                            if (user) {
                                user.status = newStatus;
                                user.is_active = action === 'approve';
                            }
                        });
                        this.selected = [];
                        window.adminToast(result.data.message, 'success');
                    })
                    .catch((error) => window.adminToast(error.message || 'Bulk action failed.', 'error'))
                    .finally(() => { this.bulkBusy = false; });
            },

            openDetail(id) {
                this.drawerOpen = true;
                this.drawerLoading = true;
                this.drawerData = null;

                fetch('/admin/users/' + id, {
                    headers: { 'Accept': 'application/json' }
                })
                    .then((response) => response.json())
                    .then((data) => { this.drawerData = data; })
                    .catch(() => window.adminToast('Could not load account details.', 'error'))
                    .finally(() => { this.drawerLoading = false; });
            },

            closeDetail() {
                this.drawerOpen = false;
            }
        };
    }

    function resetCreatePasswordStrength() {
        var strengthWrap = document.getElementById('createPasswordStrength');
        var requirements = document.getElementById('createPasswordRequirements');
        if (strengthWrap) strengthWrap.hidden = true;
        if (requirements) requirements.querySelectorAll('li').forEach((item) => item.classList.remove('met'));
    }

    (function () {
        var input = document.getElementById('createPassword');
        var strengthWrap = document.getElementById('createPasswordStrength');
        var fill = document.getElementById('createPasswordStrengthFill');
        var label = document.getElementById('createPasswordStrengthLabel');
        var requirements = document.getElementById('createPasswordRequirements');
        if (!input || !strengthWrap || !fill || !label || !requirements) return;

        var levels = [
            { min: 0, text: 'Very weak', color: '#C0392B', width: '15%' },
            { min: 1, text: 'Weak', color: '#E67E22', width: '35%' },
            { min: 2, text: 'Fair', color: '#F0A500', width: '55%' },
            { min: 3, text: 'Good', color: '#74C69D', width: '80%' },
            { min: 4, text: 'Strong', color: '#2D6A4F', width: '100%' },
        ];

        input.addEventListener('input', function () {
            var value = input.value;
            strengthWrap.hidden = value.length === 0;
            if (value.length === 0) return;

            var rules = {
                length: value.length >= 8,
                case: /[a-z]/.test(value) && /[A-Z]/.test(value),
                number: /[0-9]/.test(value),
                symbol: /[^A-Za-z0-9]/.test(value),
            };

            requirements.querySelectorAll('li').forEach(function (item) {
                var rule = item.getAttribute('data-rule');
                item.classList.toggle('met', !!rules[rule]);
            });

            var score = [rules.length, rules.case, rules.number, rules.symbol].filter(Boolean).length;
            var level = levels.slice().reverse().find(function (l) { return score >= l.min; }) || levels[0];

            fill.style.width = level.width;
            fill.style.background = level.color;
            label.textContent = level.text;
            label.style.color = level.color;
        });
    })();
</script>
@endsection
