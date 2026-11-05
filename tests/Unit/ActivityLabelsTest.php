<?php

declare(strict_types=1);

use Base\Tenant\Models\ActivityLog;
use Base\Tenant\Models\User;

test('una acción se lee en el idioma de quien mira, o como se registró', function () {
    app()->setLocale('es');

    expect(ActivityLog::actionLabel('updated'))->toBe('Actualizado')
        ->and(ActivityLog::actionLabel('account.entered'))->toBe('Entrada en la cuenta')
        ->and(ActivityLog::actionLabel('ticket.reopened'))->toBe('Ticket.reopened');
});

test('el elemento se nombra por su clase, traducida si la app la nombra', function () {
    app()->setLocale('es');

    $entrada = new ActivityLog(['subject_type' => User::class, 'action' => 'updated']);
    $ajena = new ActivityLog(['subject_type' => 'App\\Models\\KnowledgeSource', 'action' => 'updated']);

    expect($entrada->subject_label)->toBe('Usuario')
        ->and($entrada->action_label)->toBe('Actualizado')
        ->and($ajena->subject_label)->toBe('KnowledgeSource')
        ->and((new ActivityLog)->subject_label)->toBeNull();

    app('translator')->addLines(['activity.subjects.KnowledgeSource' => 'Fuente de conocimiento'], 'es', 'base-tenant');

    expect($ajena->subject_label)->toBe('Fuente de conocimiento');
});
