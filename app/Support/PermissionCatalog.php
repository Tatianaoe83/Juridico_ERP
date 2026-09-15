<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;

/**
 * Cómo se presentan los permisos en pantalla.
 *
 * Los permisos viven en la base (los crea RoleSeeder); aquí solo está el texto
 * de producto: a qué área pertenece cada uno y cómo se llama para una persona.
 * Un permiso sin etiqueta sale con su nombre técnico, así que agregar uno
 * nuevo no rompe nada.
 */
class PermissionCatalog
{
    /** Etiquetas de las áreas, deducidas del prefijo del permiso. */
    public const AREAS = [
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'calendar' => 'Calendario',
        'events' => 'Eventos',
    ];

    private const LABELS = [
        'users.view' => 'Ver usuarios',
        'users.create' => 'Crear usuarios',
        'users.update' => 'Editar usuarios',
        'users.delete' => 'Eliminar usuarios',
        'roles.manage' => 'Administrar roles y permisos',
        'calendar.view' => 'Ver calendario',
        'calendar.link' => 'Vincular Microsoft 365',
        'calendar.share' => 'Compartir calendario',
        'events.create' => 'Crear eventos',
        'events.update' => 'Editar eventos',
        'events.delete' => 'Eliminar eventos',
    ];

    /**
     * Todos los permisos existentes, agrupados por área.
     *
     * @return list<array{key: string, area: string, permissions: list<array{name: string, label: string}>}>
     */
    public static function groups(): array
    {
        return Permission::orderBy('name')
            ->pluck('name')
            ->groupBy(fn (string $name) => strtok($name, '.'))
            ->map(fn ($names, string $prefix) => [
                'key' => $prefix,
                'area' => self::AREAS[$prefix] ?? ucfirst($prefix),
                'permissions' => $names->map(fn (string $name) => [
                    'name' => $name,
                    'label' => self::LABELS[$name] ?? $name,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
