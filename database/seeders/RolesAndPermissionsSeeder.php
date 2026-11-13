<?php

namespace Database\Seeders;

use App\Enums\AppAccess;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $editorRole = Role::firstOrCreate(['name' => 'editor']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        $backOfficeAccessPermission = Permission::firstOrCreate(['name' => AppAccess::BackOffice->value]);
        $registerAccessPermission = Permission::firstOrCreate(['name' => AppAccess::Register->value]);

        $createEventPermission = Permission::firstOrCreate(['name' => 'create event']);
        $editEventPermission = Permission::firstOrCreate(['name' => 'edit event']);
        $deleteEventPermission = Permission::firstOrCreate(['name' => 'delete event']);

        $createEventChainPermission = Permission::firstOrCreate(['name' => 'create event chain']);
        $editEventChainPermission = Permission::firstOrCreate(['name' => 'edit event chain']);
        $deleteEventChainPermission = Permission::firstOrCreate(['name' => 'delete event chain']);

        $createPostPermission = Permission::firstOrCreate(['name' => 'create post']);
        $editPostPermission = Permission::firstOrCreate(['name' => 'edit post']);
        $deletePostPermission = Permission::firstOrCreate(['name' => 'delete post']);

        $createLocationPermission = Permission::firstOrCreate(['name' => 'create location']);
        $editLocationPermission = Permission::firstOrCreate(['name' => 'edit location']);
        $deleteLocationPermission = Permission::firstOrCreate(['name' => 'delete location']);

        $createContentPermission = Permission::firstOrCreate(['name' => 'create content']);
        $editContentPermission = Permission::firstOrCreate(['name' => 'edit content']);
        $deleteContentPermission = Permission::firstOrCreate(['name' => 'delete content']);

        $createUserPermission = Permission::firstOrCreate(['name' => 'create user']);
        $editUserPermission = Permission::firstOrCreate(['name' => 'edit user']);
        $deleteUserPermission = Permission::firstOrCreate(['name' => 'delete user']);

        $createMemberRegistrationPermission = Permission::firstOrCreate(['name' => 'create member registration']);
        $manageMemberRegistrationPermission = Permission::firstOrCreate(['name' => 'manage member registration']);

        // Register permissions
        $viewRegisterPermission = Permission::firstOrCreate(['name' => 'view register']);
        $openDayPermission = Permission::firstOrCreate(['name' => 'open day']);
        $closeDayPermission = Permission::firstOrCreate(['name' => 'close day']);
        $reopenDayPermission = Permission::firstOrCreate(['name' => 'reopen day']);
        $addCashRemarkPermission = Permission::firstOrCreate(['name' => 'add cash remark']);
        $createTicketPermission = Permission::firstOrCreate(['name' => 'create ticket']);
        $viewTicketsPermission = Permission::firstOrCreate(['name' => 'view tickets']);
        $refundTicketPermission = Permission::firstOrCreate(['name' => 'refund ticket']);
        $addProductToTicketPermission = Permission::firstOrCreate(['name' => 'add product to ticket']);
        $applyVoucherToTicketPermission = Permission::firstOrCreate(['name' => 'apply voucher to ticket']);
        $manageProductsPermission = Permission::firstOrCreate(['name' => 'manage products']);
        $manageCategoriesPermission = Permission::firstOrCreate(['name' => 'manage categories']);
        $manageVouchersPermission = Permission::firstOrCreate(['name' => 'manage vouchers']);
        $manageSeasonsPermission = Permission::firstOrCreate(['name' => 'manage seasons']);
        $manageMembersPermission = Permission::firstOrCreate(['name' => 'manage members']);
        $viewMemberSensitiveDataPermission = Permission::firstOrCreate(['name' => 'view member sensitive data']);
        $manageOperatingDaysPermission = Permission::firstOrCreate(['name' => 'manage operating days']);
        $manageCashMovementsPermission = Permission::firstOrCreate(['name' => 'manage cash movements']);
        $viewReportsPermission = Permission::firstOrCreate(['name' => 'view reports']);
        $manageRegisterSettingsPermission = Permission::firstOrCreate(['name' => 'manage register settings']);
        $restoreRegisterPermission = Permission::firstOrCreate(['name' => 'restore register']);

        // Stock permissions
        $manageStockPermission = Permission::firstOrCreate(['name' => 'manage stock']);
        $recordStockLossPermission = Permission::firstOrCreate(['name' => 'record stock loss']);
        $compactStockPermission = Permission::firstOrCreate(['name' => 'compact stock']);

        $cashierRole = Role::firstOrCreate(['name' => 'cashier']);
        $cashierManagerRole = Role::firstOrCreate(['name' => 'cashier manager']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);

        // Assign permissions to roles
        $adminRole->syncPermissions([
            $backOfficeAccessPermission,
            $registerAccessPermission,
            $createEventPermission,
            $editEventPermission,
            $deleteEventPermission,
            $createEventChainPermission,
            $editEventChainPermission,
            $deleteEventChainPermission,
            $createPostPermission,
            $editPostPermission,
            $deletePostPermission,
            $createLocationPermission,
            $editLocationPermission,
            $deleteLocationPermission,
            $createContentPermission,
            $editContentPermission,
            $deleteContentPermission,
            $createUserPermission,
            $editUserPermission,
            $deleteUserPermission,
            $viewRegisterPermission,
            $openDayPermission,
            $closeDayPermission,
            $reopenDayPermission,
            $addCashRemarkPermission,
            $createTicketPermission,
            $viewTicketsPermission,
            $refundTicketPermission,
            $addProductToTicketPermission,
            $applyVoucherToTicketPermission,
            $manageProductsPermission,
            $manageCategoriesPermission,
            $manageVouchersPermission,
            $manageSeasonsPermission,
            $manageMembersPermission,
            $viewMemberSensitiveDataPermission,
            $manageOperatingDaysPermission,
            $manageCashMovementsPermission,
            $viewReportsPermission,
            $manageRegisterSettingsPermission,
            $restoreRegisterPermission,
            $createMemberRegistrationPermission,
            $manageMemberRegistrationPermission,
            $manageStockPermission,
            $recordStockLossPermission,
            $compactStockPermission,
        ]);

        $editorRole->syncPermissions([
            $backOfficeAccessPermission,
            $createEventPermission,
            $editEventPermission,
            $deleteEventPermission,
            $createEventChainPermission,
            $editEventChainPermission,
            $deleteEventChainPermission,
            $createPostPermission,
            $editPostPermission,
            $deletePostPermission,
            $createLocationPermission,
            $editLocationPermission,
            $deleteLocationPermission,
            $createContentPermission,
            $editContentPermission,
            $deleteContentPermission,
        ]);

        $cashierRole->syncPermissions([
            $registerAccessPermission,
            $viewRegisterPermission,
            $openDayPermission,
            $closeDayPermission,
            $addCashRemarkPermission,
            $createTicketPermission,
            $viewTicketsPermission,
            $addProductToTicketPermission,
            $applyVoucherToTicketPermission,
            $recordStockLossPermission,
        ]);

        $cashierManagerRole->syncPermissions([
            $backOfficeAccessPermission,
            $registerAccessPermission,
            $viewRegisterPermission,
            $openDayPermission,
            $closeDayPermission,
            $reopenDayPermission,
            $addCashRemarkPermission,
            $createTicketPermission,
            $viewTicketsPermission,
            $refundTicketPermission,
            $addProductToTicketPermission,
            $applyVoucherToTicketPermission,
            $manageProductsPermission,
            $manageCategoriesPermission,
            $manageVouchersPermission,
            $manageSeasonsPermission,
            $manageOperatingDaysPermission,
            $manageCashMovementsPermission,
            $viewReportsPermission,
            $manageRegisterSettingsPermission,
            $restoreRegisterPermission,
            $manageStockPermission,
            $recordStockLossPermission,
        ]);

        $accountantRole->syncPermissions([
            $backOfficeAccessPermission,
            $registerAccessPermission,
            $viewRegisterPermission,
            $viewTicketsPermission,
            $viewReportsPermission,
        ]);

        $memberManagerRole = Role::firstOrCreate(['name' => 'member manager']);
        $memberManagerRole->syncPermissions([
            $backOfficeAccessPermission,
            $createMemberRegistrationPermission,
            $manageMemberRegistrationPermission,
            $manageMembersPermission,
        ]);

        // Role used as mailing list for members
        $getMemberRegistrationMailsRole = Role::firstOrCreate(['name' => 'get member registration mails']);
        $getRegisterCloseOperatingDayRemarkRole = Role::firstOrCreate(['name' => 'get register close operating day remark']);
    }
}
