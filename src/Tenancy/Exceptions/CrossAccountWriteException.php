<?php

declare(strict_types=1);

namespace Base\Tenant\Tenancy\Exceptions;

/**
 * A write would reach a row owned by an account other than the one in context,
 * or would move a row from one account to another.
 */
class CrossAccountWriteException extends TenancyException
{
    public static function contextMismatch(string $model, string $operation, ?string $recordAccountId, ?string $contextAccountId): self
    {
        return new self(sprintf(
            'Refusing to %s [%s]: the record belongs to account [%s] but account [%s] is in context.',
            $operation,
            $model,
            $recordAccountId ?? 'none',
            $contextAccountId ?? 'none',
        ));
    }

    public static function immutableAccountId(string $model, ?string $from, ?string $to): self
    {
        return new self(sprintf(
            'The account of [%s] cannot change once created (from [%s] to [%s]).',
            $model,
            $from ?? 'none',
            $to ?? 'none',
        ));
    }

    public static function foreignRow(string $model, string $operation, ?string $rowAccountId, string $contextAccountId): self
    {
        return new self(sprintf(
            'Refusing to %s [%s]: a row names account [%s] but account [%s] is in context.',
            $operation,
            $model,
            $rowAccountId ?? 'none',
            $contextAccountId,
        ));
    }

    public static function pivotParentMismatch(string $model, string $table, string $operation, ?string $parentAccountId, ?string $contextAccountId): self
    {
        return new self(sprintf(
            'Refusing to %s on pivot [%s]: the parent [%s] belongs to account [%s] but account [%s] is in context.',
            $operation,
            $table,
            $model,
            $parentAccountId ?? 'none',
            $contextAccountId ?? 'none',
        ));
    }
}
