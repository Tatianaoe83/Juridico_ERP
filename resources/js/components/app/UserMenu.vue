<script setup>
import { router } from '@inertiajs/vue3';
import { ChevronDown, LogOut } from 'lucide-vue-next';
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
            class="group flex h-10 cursor-pointer items-center gap-2.5 rounded-full py-0.5 pr-2 pl-0.5 transition-colors duration-150 hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-brand/30 data-[state=open]:bg-slate-100 dark:hover:bg-white/[0.06] dark:focus-visible:ring-white/30 dark:data-[state=open]:bg-white/[0.06]"
            :aria-label="`Menú de ${user.name}`"
        >
            <span
                class="grid size-9 shrink-0 place-content-center rounded-full bg-gradient-to-br from-brand-light to-brand text-xs font-bold tracking-wide text-white shadow-sm shadow-brand/30"
            >
                {{ initials }}
            </span>
            <!-- En móvil solo quedan las iniciales: el nombre no cabe junto a la ruta -->
            <span class="hidden max-w-44 truncate text-sm font-semibold text-brand sm:block dark:text-white">
                {{ user.name }}
            </span>
            <ChevronDown
                class="size-4 shrink-0 text-slate-400 transition-transform duration-200 group-data-[state=open]:rotate-180 motion-reduce:transition-none dark:text-brand-gray"
            />
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" :side-offset="8" class="w-60 font-corporate">
            <DropdownMenuLabel class="font-normal">
                <span class="block truncate text-sm font-semibold">{{ user.name }}</span>
                <span v-if="user.email" class="block truncate text-xs text-muted-foreground">{{ user.email }}</span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem variant="destructive" class="cursor-pointer" @select="logout">
                <LogOut class="size-4" />
                Cerrar sesión
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
