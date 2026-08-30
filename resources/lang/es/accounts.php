<?php

return [
    // Management
    'management_title' => 'Gestión de Cuentas',
    'management_description' => 'Todas las cuentas a las que llegas, con su plan y cuánta gente hay en cada una.',
    'created' => 'Creada',
    'empty_description' => 'Crea la primera cuenta para empezar a trabajar.',
    'filter_subscription' => 'Suscripción',
    'all_subscriptions' => 'Cualquier suscripción',
    'subscription_active' => 'Activa',
    'subscription_trialling' => 'En prueba',
    'subscription_none' => 'Sin suscripción',
    'add_new' => 'Agregar Nueva Cuenta',
    'search_placeholder' => 'Buscar cuentas...',

    // Table headers
    'name' => 'Nombre de la Cuenta',
    'users_count' => 'Usuarios',
    'status' => 'Estado',
    'actions' => 'Acciones',
    'owner_short' => 'Propietario',
    'no_owner' => 'Sin asignar',
    'no_email' => 'Sin correo de contacto',
    'inactive_summary' => '{1} :count cuenta está inactiva y nadie puede trabajar dentro|[2,*] :count cuentas están inactivas y nadie puede trabajar dentro',

    // Status
    'active' => 'Activa',
    'inactive' => 'Inactiva',
    'users' => 'usuarios',

    // Actions
    'edit' => 'Editar',
    'delete' => 'Eliminar',
    'cancel' => 'Cancelar',

    // Messages
    'no_accounts_found' => 'No se encontraron cuentas.',
    'account_created' => 'Cuenta Creada',
    'created_successfully' => 'La cuenta se ha creado exitosamente.',
    'account_updated' => 'Cuenta Actualizada',
    'updated_successfully' => 'La cuenta se ha actualizado exitosamente.',
    'account_removed' => 'Cuenta Eliminada',
    'removed_successfully' => 'La cuenta se ha eliminado exitosamente.',
    'error_deleting_account' => 'Error al Eliminar Cuenta',
    'cannot_delete_with_users' => 'No se puede eliminar una cuenta que tiene usuarios. Por favor, elimine todos los usuarios primero.',
    'cannot_delete_with_projects' => 'No se puede eliminar una cuenta que tiene proyectos. Por favor, elimine todos los proyectos primero.',
    'cannot_delete_with_translations' => 'No se puede eliminar una cuenta que tiene traducciones. Por favor, elimine todas las traducciones primero.',
    'cannot_delete_with_relations' => 'No se puede eliminar una cuenta que tiene datos relacionados en las siguientes tablas: :tables',

    // Delete confirmation
    'delete_account' => 'Eliminar Cuenta',
    'delete_confirmation' => '¿Está seguro de que desea eliminar esta cuenta? Esta acción no se puede deshacer.',

    // Edit/Create
    'back_to_accounts' => 'Volver a Cuentas',
    'create_new_account' => 'Crear Nueva Cuenta',
    'edit_account_title' => 'Editar Cuenta: :name',
    'create_account' => 'Crear Cuenta',
    'save_account' => 'Guardar Cambios',

    // Form fields
    'account_information' => 'Información de la Cuenta',
    'owner' => 'Propietario de la Cuenta',
    'select_owner' => 'Seleccionar propietario...',
    'email' => 'Correo Electrónico',
    'phone' => 'Teléfono',
    'address' => 'Dirección',
    'city' => 'Ciudad',
    'state' => 'Estado/Provincia',
    'country' => 'País',
    'postal_code' => 'Código Postal',
    'vat' => 'CIF/NIF',

    // Account users
    'edit_account_description' => 'Todo lo de esta página se guarda junto. Las personas de la cuenta se listan abajo, pero se editan desde su propia ficha.',
    'account_information_description' => 'El nombre por el que se conoce la cuenta, quién la posee y si se puede usar siquiera.',
    'contact_details' => 'Contacto y Facturación',
    'contact_details_description' => 'Dónde se localiza a la cuenta y qué va en sus facturas. Nada de esto es obligatorio.',
    'active_description' => 'Una cuenta inactiva conserva todos sus datos, pero nadie puede trabajar dentro.',
    'account_users' => 'Usuarios de la Cuenta',
    'account_users_description' => 'Usuarios pertenecientes a esta cuenta.',

    // Force password change
    'force_password_change' => 'Requerir cambio de contraseña en primer inicio de sesión',
    'force_password_change_description' => 'Controla si los nuevos usuarios de esta cuenta deben cambiar su contraseña en el primer inicio de sesión.',
    'force_password_inherit' => 'Heredar de configuración global',
    'force_password_inherit_description' => 'Usar la configuración global (actualmente habilitada)',
    'force_password_enabled' => 'Siempre requerir',
    'force_password_enabled_description' => 'Siempre requerir cambio de contraseña para nuevos usuarios en esta cuenta',
    'force_password_disabled' => 'Nunca requerir',
    'force_password_disabled_description' => 'Nunca requerir cambio de contraseña para nuevos usuarios en esta cuenta',
    'no_account' => 'Sin cuenta',
    'staff' => 'Plataforma',
    'staff_entered' => ':name ha entrado en esta cuenta desde la administración de la plataforma.',
    'none' => 'No hay cuentas',
    'leave' => 'Salir de esta cuenta',

];
