<script setup>
import { router } from '@inertiajs/vue3';
import { ChevronsUpDown, LogOut } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

const props = defineProps({
    user: { type: Object, required: true },
});

const initials = computed(() =>
    (props.user.name ?? '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="flex w-full items-center gap-2.5 rounded-md p-2 text-left transition-colors hover:bg-accent"
        >
            <span
                class="grid size-8 shrink-0 place-content-center rounded-md bg-primary text-xs font-semibold text-primary-foreground"
            >
                {{ initials }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block truncate text-sm font-medium">{{ user.name }}</span>
                <span class="block truncate text-xs text-muted-foreground">{{ user.email }}</span>
            </span>
            <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
        </DropdownMenuTrigger>

        <DropdownMenuContent align="start" side="top" class="w-56">
            <DropdownMenuLabel>
                <span class="block truncate">{{ user.name }}</span>
                <span class="block truncate text-xs font-normal text-muted-foreground">
                    {{ (user.roles ?? []).join(', ') || 'Sin rol' }}
                </span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem @select="logout">
                <LogOut class="size-4" />
                Cerrar sesión
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
