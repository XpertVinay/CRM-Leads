<?php

namespace Database\Seeders;

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\RbacMenu;
use App\Modules\Tenancy\Models\RbacPermission;
use App\Modules\Tenancy\Models\RbacRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class RbacSeeder extends Seeder
{
    /**
     * Default permission definitions and tightened role-permission matrix.
     *
     * @return array<int, array{slug: string, name: string, module: string, is_menu_item: bool, sort_order: int, roles: array<int, string>}>
     */
    public static function definitions(): array
    {
        $allRoles = TenantRole::values();
        $adminAndManager = [TenantRole::OrgAdmin->value, TenantRole::SalesManager->value];
        $writers = [TenantRole::OrgAdmin->value, TenantRole::SalesManager->value, TenantRole::SalesRep->value];
        $adminOnly = [TenantRole::OrgAdmin->value];

        return [
            // Dashboard
            ['slug' => 'dashboard.view', 'name' => 'View Dashboard', 'module' => 'Dashboard', 'is_menu_item' => true, 'sort_order' => 10, 'roles' => $allRoles],

            // Leads
            ['slug' => 'leads.view', 'name' => 'View Leads', 'module' => 'Leads', 'is_menu_item' => true, 'sort_order' => 20, 'roles' => $allRoles],
            ['slug' => 'leads.create', 'name' => 'Create Leads', 'module' => 'Leads', 'is_menu_item' => false, 'sort_order' => 21, 'roles' => $writers],
            ['slug' => 'leads.update', 'name' => 'Update Leads', 'module' => 'Leads', 'is_menu_item' => false, 'sort_order' => 22, 'roles' => $writers],
            ['slug' => 'leads.convert', 'name' => 'Convert Leads', 'module' => 'Leads', 'is_menu_item' => false, 'sort_order' => 23, 'roles' => $writers],
            ['slug' => 'leads.assign', 'name' => 'Assign Leads', 'module' => 'Leads', 'is_menu_item' => false, 'sort_order' => 24, 'roles' => $adminAndManager],
            ['slug' => 'leads.delete', 'name' => 'Delete Leads', 'module' => 'Leads', 'is_menu_item' => false, 'sort_order' => 25, 'roles' => $adminAndManager],

            // Tracking Links
            ['slug' => 'tracking_links.view', 'name' => 'View Tracking Links', 'module' => 'Tracking', 'is_menu_item' => true, 'sort_order' => 30, 'roles' => $allRoles],
            ['slug' => 'tracking_links.create', 'name' => 'Create Tracking Links', 'module' => 'Tracking', 'is_menu_item' => false, 'sort_order' => 31, 'roles' => $writers],
            ['slug' => 'tracking_links.update', 'name' => 'Update Tracking Links', 'module' => 'Tracking', 'is_menu_item' => false, 'sort_order' => 32, 'roles' => $writers],
            ['slug' => 'tracking_links.delete', 'name' => 'Delete Tracking Links', 'module' => 'Tracking', 'is_menu_item' => false, 'sort_order' => 33, 'roles' => $adminAndManager],

            // Campaigns
            ['slug' => 'campaigns.view', 'name' => 'View Campaigns', 'module' => 'Campaigns', 'is_menu_item' => true, 'sort_order' => 40, 'roles' => $allRoles],
            ['slug' => 'campaigns.create', 'name' => 'Create Campaigns', 'module' => 'Campaigns', 'is_menu_item' => false, 'sort_order' => 41, 'roles' => $adminAndManager],
            ['slug' => 'campaigns.update', 'name' => 'Update Campaigns', 'module' => 'Campaigns', 'is_menu_item' => false, 'sort_order' => 42, 'roles' => $adminAndManager],
            ['slug' => 'campaigns.delete', 'name' => 'Delete Campaigns', 'module' => 'Campaigns', 'is_menu_item' => false, 'sort_order' => 43, 'roles' => $adminAndManager],

            // Team, Invitations & API Tokens
            ['slug' => 'team.manage', 'name' => 'Manage Employees & Invitations', 'module' => 'Team & Security', 'is_menu_item' => true, 'sort_order' => 50, 'roles' => $adminOnly],
            ['slug' => 'tokens.manage', 'name' => 'Manage API Tokens', 'module' => 'Team & Security', 'is_menu_item' => false, 'sort_order' => 51, 'roles' => $adminOnly],

            // Follow-ups
            ['slug' => 'follow_ups.view', 'name' => 'View Follow-ups', 'module' => 'Follow-ups', 'is_menu_item' => true, 'sort_order' => 60, 'roles' => $allRoles],
            ['slug' => 'follow_ups.create', 'name' => 'Create Follow-ups', 'module' => 'Follow-ups', 'is_menu_item' => false, 'sort_order' => 61, 'roles' => $writers],
            ['slug' => 'follow_ups.update', 'name' => 'Update Follow-ups', 'module' => 'Follow-ups', 'is_menu_item' => false, 'sort_order' => 62, 'roles' => $writers],
            ['slug' => 'follow_ups.delete', 'name' => 'Delete Follow-ups', 'module' => 'Follow-ups', 'is_menu_item' => false, 'sort_order' => 63, 'roles' => $adminAndManager],

            // Reports & Analytics (Manager, Viewer & Org Admin by default; Sales Rep excluded by default)
            ['slug' => 'reports.view', 'name' => 'View Reports & Export', 'module' => 'Analytics & Reports', 'is_menu_item' => true, 'sort_order' => 70, 'roles' => [TenantRole::OrgAdmin->value, TenantRole::SalesManager->value, TenantRole::ReadOnly->value]],
            ['slug' => 'analytics.view', 'name' => 'View Analytics Dashboards', 'module' => 'Analytics & Reports', 'is_menu_item' => true, 'sort_order' => 71, 'roles' => [TenantRole::OrgAdmin->value, TenantRole::SalesManager->value, TenantRole::ReadOnly->value]],

            // Organization, Custom Fields & Billing
            ['slug' => 'organization.settings', 'name' => 'Organization Settings', 'module' => 'Settings', 'is_menu_item' => true, 'sort_order' => 80, 'roles' => $adminOnly],
            ['slug' => 'custom_fields.view', 'name' => 'View Custom Fields', 'module' => 'Settings', 'is_menu_item' => false, 'sort_order' => 81, 'roles' => $allRoles],
            ['slug' => 'custom_fields.manage', 'name' => 'Manage Custom Fields', 'module' => 'Settings', 'is_menu_item' => true, 'sort_order' => 82, 'roles' => $adminOnly],
            ['slug' => 'subscription.view', 'name' => 'View Subscription & Billing', 'module' => 'Settings', 'is_menu_item' => true, 'sort_order' => 83, 'roles' => $adminOnly],

            // Accounts & Contacts
            ['slug' => 'accounts.view', 'name' => 'View Accounts', 'module' => 'Pipeline', 'is_menu_item' => true, 'sort_order' => 90, 'roles' => $allRoles],
            ['slug' => 'accounts.create', 'name' => 'Create Accounts', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 92, 'roles' => $writers],
            ['slug' => 'accounts.update', 'name' => 'Update Accounts', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 93, 'roles' => $writers],
            ['slug' => 'accounts.delete', 'name' => 'Delete Accounts', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 94, 'roles' => $adminAndManager],

            ['slug' => 'contacts.view', 'name' => 'View Contacts', 'module' => 'Pipeline', 'is_menu_item' => true, 'sort_order' => 100, 'roles' => $allRoles],
            ['slug' => 'contacts.create', 'name' => 'Create Contacts', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 102, 'roles' => $writers],
            ['slug' => 'contacts.update', 'name' => 'Update Contacts', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 103, 'roles' => $writers],
            ['slug' => 'contacts.delete', 'name' => 'Delete Contacts', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 104, 'roles' => $adminAndManager],

            // Deals & Pipelines
            ['slug' => 'deals.view', 'name' => 'View Deals', 'module' => 'Pipeline', 'is_menu_item' => true, 'sort_order' => 110, 'roles' => $allRoles],
            ['slug' => 'deals.create', 'name' => 'Create Deals', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 111, 'roles' => $writers],
            ['slug' => 'deals.update', 'name' => 'Update & Transition Deals', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 112, 'roles' => $writers],
            ['slug' => 'deals.delete', 'name' => 'Delete Deals', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 113, 'roles' => $adminAndManager],

            ['slug' => 'pipelines.view', 'name' => 'View Pipelines', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 120, 'roles' => $allRoles],
            ['slug' => 'pipelines.create', 'name' => 'Create Pipelines', 'module' => 'Pipeline', 'is_menu_item' => true, 'sort_order' => 122, 'roles' => $adminAndManager],
            ['slug' => 'pipelines.update', 'name' => 'Update Pipelines', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 123, 'roles' => $adminAndManager],
            ['slug' => 'pipelines.delete', 'name' => 'Delete Pipelines', 'module' => 'Pipeline', 'is_menu_item' => false, 'sort_order' => 124, 'roles' => $adminOnly],

            // WhatsApp
            ['slug' => 'whatsapp.view', 'name' => 'View WhatsApp Conversations', 'module' => 'WhatsApp', 'is_menu_item' => true, 'sort_order' => 130, 'roles' => $allRoles],
            ['slug' => 'whatsapp.send', 'name' => 'Send WhatsApp Messages & Consent', 'module' => 'WhatsApp', 'is_menu_item' => false, 'sort_order' => 132, 'roles' => $writers],
            ['slug' => 'whatsapp.settings', 'name' => 'Manage WhatsApp API Settings', 'module' => 'WhatsApp', 'is_menu_item' => true, 'sort_order' => 133, 'roles' => $adminOnly],

            // Compliance
            ['slug' => 'audit_logs.view', 'name' => 'View Audit Logs', 'module' => 'Compliance', 'is_menu_item' => true, 'sort_order' => 140, 'roles' => $adminOnly],
        ];
    }

    /**
     * Default sidebar menu items for the portal.
     *
     * @return array<int, array{label: string, route_name: string, route_pattern: string, icon: string, permission_slug: ?string, section: string, superadmin_only: bool, requires_org: bool, sort_order: int}>
     */
    public static function defaultMenus(): array
    {
        return [
            ['label' => 'Dashboard', 'route_name' => 'dashboard', 'route_pattern' => 'dashboard', 'icon' => 'dashboard', 'permission_slug' => 'dashboard.view', 'section' => 'main', 'superadmin_only' => false, 'requires_org' => false, 'sort_order' => 10],
            ['label' => 'Leads', 'route_name' => 'leads.index', 'route_pattern' => 'leads.*', 'icon' => 'inbox', 'permission_slug' => 'leads.view', 'section' => 'main', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 20],
            ['label' => 'Tracking Links', 'route_name' => 'tracking-links.index', 'route_pattern' => 'tracking-links.*', 'icon' => 'link', 'permission_slug' => 'tracking_links.view', 'section' => 'main', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 30],
            ['label' => 'Campaigns', 'route_name' => 'campaigns.index', 'route_pattern' => 'campaigns.*', 'icon' => 'campaign', 'permission_slug' => 'campaigns.view', 'section' => 'main', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 40],
            ['label' => 'Employees', 'route_name' => 'settings.team', 'route_pattern' => 'settings.team', 'icon' => 'group', 'permission_slug' => 'team.manage', 'section' => 'main', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 50],
            ['label' => 'Follow-ups', 'route_name' => 'follow-ups.index', 'route_pattern' => 'follow-ups.*', 'icon' => 'event_upcoming', 'permission_slug' => 'follow_ups.view', 'section' => 'main', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 60],
            ['label' => 'Reports', 'route_name' => 'reports.index', 'route_pattern' => 'reports.*', 'icon' => 'bar_chart', 'permission_slug' => 'reports.view', 'section' => 'main', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 70],
            ['label' => 'Organization Settings', 'route_name' => 'settings.organization', 'route_pattern' => 'settings.organization', 'icon' => 'settings', 'permission_slug' => 'organization.settings', 'section' => 'main', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 80],

            // Pipeline & Channels
            ['label' => 'Accounts', 'route_name' => 'accounts.index', 'route_pattern' => 'accounts.*', 'icon' => 'business', 'permission_slug' => 'accounts.view', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 90],
            ['label' => 'Contacts', 'route_name' => 'contacts.index', 'route_pattern' => 'contacts.*', 'icon' => 'contacts', 'permission_slug' => 'contacts.view', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 100],
            ['label' => 'Deals', 'route_name' => 'deals.index', 'route_pattern' => 'deals.*', 'icon' => 'handshake', 'permission_slug' => 'deals.view', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 110],
            ['label' => 'WhatsApp Chat', 'route_name' => 'whatsapp.chat', 'route_pattern' => 'whatsapp.chat', 'icon' => 'chat', 'permission_slug' => 'whatsapp.view', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 120],
            ['label' => 'Analytics', 'route_name' => 'analytics.executive', 'route_pattern' => 'analytics.*', 'icon' => 'analytics', 'permission_slug' => 'analytics.view', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 130],
            ['label' => 'Subscription', 'route_name' => 'subscription.index', 'route_pattern' => 'subscription.*', 'icon' => 'credit_card', 'permission_slug' => 'subscription.view', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 140],
            ['label' => 'Pipelines', 'route_name' => 'pipelines.index', 'route_pattern' => 'pipelines.*', 'icon' => 'view_kanban', 'permission_slug' => 'pipelines.create', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 150],
            ['label' => 'Custom Fields', 'route_name' => 'settings.custom-fields', 'route_pattern' => 'settings.custom-fields', 'icon' => 'tune', 'permission_slug' => 'custom_fields.manage', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 160],
            ['label' => 'WhatsApp API', 'route_name' => 'whatsapp.settings', 'route_pattern' => 'whatsapp.settings', 'icon' => 'api', 'permission_slug' => 'whatsapp.settings', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 170],
            ['label' => 'Audit Logs', 'route_name' => 'audit-logs.index', 'route_pattern' => 'audit-logs.*', 'icon' => 'history', 'permission_slug' => 'audit_logs.view', 'section' => 'pipeline', 'superadmin_only' => false, 'requires_org' => true, 'sort_order' => 180],

            // Superadmin-only Portal Menus
            ['label' => 'Organizations', 'route_name' => 'organizations.index', 'route_pattern' => 'organizations.*', 'icon' => 'corporate_fare', 'permission_slug' => null, 'section' => 'superadmin', 'superadmin_only' => true, 'requires_org' => false, 'sort_order' => 190],
            ['label' => 'Roles & Permissions', 'route_name' => 'superadmin.rbac.index', 'route_pattern' => 'superadmin.rbac.*', 'icon' => 'admin_panel_settings', 'permission_slug' => null, 'section' => 'superadmin', 'superadmin_only' => true, 'requires_org' => false, 'sort_order' => 200],
            ['label' => 'Portal Menus', 'route_name' => 'superadmin.menus.index', 'route_pattern' => 'superadmin.menus.*', 'icon' => 'menu_open', 'permission_slug' => null, 'section' => 'superadmin', 'superadmin_only' => true, 'requires_org' => false, 'sort_order' => 210],
            ['label' => 'Coupons', 'route_name' => 'coupons.index', 'route_pattern' => 'coupons.*', 'icon' => 'local_offer', 'permission_slug' => null, 'section' => 'superadmin', 'superadmin_only' => true, 'requires_org' => true, 'sort_order' => 220],
        ];
    }

    public function run(): void
    {
        self::seedDefaults();
    }

    public static function seedDefaults(): void
    {
        $roleModels = [];
        foreach (TenantRole::cases() as $role) {
            $roleModels[$role->value] = RbacRole::query()->firstOrCreate(
                ['slug' => $role->value],
                [
                    'name' => $role->label(),
                    'description' => 'Default system role: ' . $role->label(),
                    'is_system' => true,
                ]
            );
        }

        $rolePermissions = [];
        foreach (TenantRole::values() as $slug) {
            $rolePermissions[$slug] = [];
        }

        foreach (self::definitions() as $def) {
            $permission = RbacPermission::query()->firstOrCreate(
                ['slug' => $def['slug']],
                [
                    'name' => $def['name'],
                    'module' => $def['module'],
                    'is_menu_item' => $def['is_menu_item'],
                    'sort_order' => $def['sort_order'],
                ]
            );

            foreach ($def['roles'] as $roleSlug) {
                $rolePermissions[$roleSlug][] = $permission->id;
            }
        }

        foreach ($roleModels as $slug => $roleModel) {
            if ($roleModel->permissions()->count() === 0 && ! empty($rolePermissions[$slug])) {
                $roleModel->permissions()->sync($rolePermissions[$slug]);
            }
        }

        self::seedMenus();
    }

    public static function seedMenus(): void
    {
        if (! Schema::hasTable('rbac_menus')) {
            return;
        }

        foreach (self::defaultMenus() as $menu) {
            RbacMenu::query()->firstOrCreate(
                ['route_name' => $menu['route_name']],
                $menu + ['is_active' => true]
            );
        }
    }
}
