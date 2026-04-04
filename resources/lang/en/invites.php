<?php

return [
    'title' => 'Invite Users',
    'description' => 'Invite users to join your account. They will receive an email with a registration link.',
    'emails_label' => 'Email addresses',
    'emails_placeholder' => "user1@example.com, user2@example.com\nuser3@example.com",
    'emails_help' => 'Separate multiple emails with commas, semicolons, or new lines.',
    'send_button' => 'Send Invitations',
    'list_title' => 'Sent Invitations',
    'no_invitations' => 'No invitations sent yet.',
    'invitations_sent' => 'Invitations Sent',
    'results' => ':sent invitations sent, :skipped skipped.',
    'invalid_emails' => ':count invalid emails ignored.',
    'error' => 'Error',
    'no_account' => 'No account context found.',
    'email_mismatch' => 'The email address must match the invitation email.',
    'token_already_used' => 'This invitation has already been used. Please contact your account administrator for a new invitation.',

    'table' => [
        'email' => 'Email',
        'status' => 'Status',
        'sent_at' => 'Sent',
        'used_at' => 'Used',
    ],

    'status' => [
        'pending' => 'Pending',
        'used' => 'Registered',
    ],

    'email_subject' => 'You have been invited to :account',
    'email_line1' => 'You have been invited to join :account. Click the button below to create your account.',
    'email_action' => 'Register Now',
    'email_line2' => 'If you did not expect this invitation, you can ignore this email.',
];
