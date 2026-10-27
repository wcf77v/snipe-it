<?php

return [
    'does_not_exist' => 'SIM card does not exist or you do not have permission to view it.',
    'lookup_multiple' => 'Several SIM cards match ":term". Refine the search below.',
    'user_has_sims' => 'This user still has SIM cards checked out. Please check them in before deleting the user.',

    'create' => [
        'error' => 'SIM card was not created, please try again.',
        'success' => 'SIM card created successfully.',
    ],

    'update' => [
        'error' => 'SIM card was not updated, please try again.',
        'success' => 'SIM card updated successfully.',
    ],

    'delete' => [
        'success' => 'SIM card deleted successfully.',
        'assigned' => 'This SIM card is currently checked out and cannot be deleted. Please check it in first.',
    ],

    'restore' => [
        'success' => 'SIM card restored successfully.',
        'duplicate' => 'Another SIM card with the same ICCID already exists, so this one cannot be restored.',
    ],

    'checkout' => [
        'success' => 'SIM card checked out successfully.',
        'error' => 'SIM card was not checked out, please try again.',
        'not_available' => 'This SIM card is not available for checkout. It may already be checked out, deleted, or have an undeployable status.',
        'company_mismatch' => 'This SIM card belongs to a different company than the checkout target.',
    ],

    'checkin' => [
        'success' => 'SIM card checked in successfully.',
        'error' => 'SIM card was not checked in, please try again.',
        'not_assigned' => 'This SIM card is not checked out.',
    ],

    'settings' => [
        'success' => 'SIM settings saved.',
        'normalized' => 'Recalculated phone numbers: :count user(s) updated.',
    ],

    'assign_not_allowed' => 'You are not allowed to check SIM cards out to users.',
    'invalid_country' => 'Unknown country code.',
    'invalid_phone' => 'This is not a valid phone number for the selected country. Example: :example',

    'plan' => [
        'create_success' => 'SIM plan created successfully.',
        'update_success' => 'SIM plan updated successfully.',
        'delete_success' => 'SIM plan deleted successfully.',
        'has_sims' => 'This plan still has SIM cards and cannot be deleted.',
    ],

    'matches' => [
        'linked' => ':count SIM card(s) checked out to their users.',
        'skipped' => ':count SIM card(s) were skipped (no longer matching, already checked out, or not allowed).',
    ],
];
