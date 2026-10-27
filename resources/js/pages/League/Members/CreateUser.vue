<script setup lang="ts">
import { computed } from 'vue';
import FormError from '../../../components/FormError.vue';
import AppIcon from '../../../components/AppIcon.vue';
import AppLayout from '../../../layouts/AppLayout.vue';
import type { RoleOption } from '../../../types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{ roles: RoleOption[] }>();
const form = useForm({ name: '', email: '', phone: '', password: '', password_confirmation: '', role_id: null as number | null, reason: '' });
const selectedRole = computed(() => props.roles.find((role) => role.id === form.role_id) ?? null);
</script>

<template>
    <Head title="Crear usuario de liga" />
    <AppLayout>
        <section class="mx-auto max-w-5xl">
            <div class="page-hero overflow-hidden rounded-[1.85rem] border border-white/70 shadow-xl shadow-slate-900/10">
                <div class="relative z-10 flex flex-col gap-5 px-6 py-7 sm:px-8 lg:flex-row lg:items-start lg:justify-between lg:px-10 lg:py-9">
                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-[0.26em] text-white/70">Miembros · alta de acceso</p>
                        <h1 class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-[2.2rem]">Crear usuario</h1>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-white/80 sm:text-base">
                            Registra un nuevo integrante para la liga, asigna su rol inicial y genera su acceso temporal de forma segura.
                        </p>
                    </div>
                    <Link href="/liga/miembros" class="hero-back-button">
                        <AppIcon name="history" :size="18" />
                        Volver
                    </Link>
                </div>
            </div>

            <form class="-mt-10 space-y-6" @submit.prevent="form.post('/liga/miembros/crear-usuario')">
                <div class="create-user-shell rounded-[1.9rem] border border-white/80 bg-white/96 p-4 shadow-2xl shadow-slate-900/10 backdrop-blur sm:p-6 lg:p-7">
                    <div class="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
                        <section class="rounded-[1.5rem] border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6">
                            <div class="mb-5 flex items-start gap-4">
                                <div class="section-icon"><AppIcon name="user" variant="illustrated" :size="46" /></div>
                                <div>
                                    <p class="form-section-eyebrow">Datos generales</p>
                                    <h2 class="form-section-title">Información del usuario</h2>
                                    <p class="mt-1 text-sm text-slate-500">Captura los datos principales para crear el acceso dentro de la liga.</p>
                                </div>
                            </div>

                            <div class="space-y-5">
                                <div>
                                    <label class="form-label">Nombre completo<span class="form-required">*</span></label>
                                    <div class="input-with-icon">
                                        <span class="input-icon"><AppIcon name="user" /></span>
                                        <input v-model="form.name" class="form-input input-with-prefix" required maxlength="150" placeholder="Nombre y apellidos" />
                                    </div>
                                    <FormError :message="form.errors.name" />
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label class="form-label">Correo electrónico<span class="form-required">*</span></label>
                                        <div class="input-with-icon">
                                            <span class="input-icon"><AppIcon name="mail" /></span>
                                            <input v-model="form.email" class="form-input input-with-prefix" type="email" required placeholder="correo@dominio.com" />
                                        </div>
                                        <FormError :message="form.errors.email" />
                                    </div>
                                    <div>
                                        <label class="form-label">Teléfono</label>
                                        <div class="input-with-icon">
                                            <span class="input-icon"><AppIcon name="phone" /></span>
                                            <input v-model="form.phone" class="form-input input-with-prefix" maxlength="30" placeholder="951 000 0000" />
                                        </div>
                                        <FormError :message="form.errors.phone" />
                                    </div>
                                </div>

                                <div>
                                    <label class="form-label">Rol inicial<span class="form-required">*</span></label>
                                    <div class="input-with-icon">
                                        <span class="input-icon"><AppIcon name="badge" /></span>
                                        <select v-model="form.role_id" class="form-input input-with-prefix pr-10" required>
                                            <option :value="null" disabled>Selecciona un rol</option>
                                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ role.name }}</option>
                                        </select>
                                    </div>
                                    <FormError :message="form.errors.role_id" />
                                </div>

                                <div>
                                    <label class="form-label">Detalles del alta<span class="form-required">*</span></label>
                                    <textarea v-model="form.reason" class="form-input min-h-32" required maxlength="500" placeholder="Describe brevemente el motivo del alta, por ejemplo: incorporación para coordinar la temporada o registro de nuevo representante."></textarea>
                                    <div class="mt-2 flex items-center justify-between gap-3 text-xs text-slate-400">
                                        <span>Este motivo se utilizará como respaldo administrativo.</span>
                                        <span>{{ form.reason.length }}/500</span>
                                    </div>
                                    <FormError :message="form.errors.reason" />
                                </div>
                            </div>
                        </section>

                        <div class="space-y-6">
                            <section class="rounded-[1.5rem] border border-slate-200/80 bg-slate-50/70 p-5 shadow-sm sm:p-6">
                                <div class="mb-5 flex items-start gap-4">
                                    <div class="section-icon"><AppIcon name="lock" variant="illustrated" :size="46" /></div>
                                    <div>
                                        <p class="form-section-eyebrow">Seguridad</p>
                                        <h2 class="form-section-title">Credenciales temporales</h2>
                                        <p class="mt-1 text-sm text-slate-500">Define una contraseña provisional. El usuario deberá cambiarla en su primer acceso.</p>
                                    </div>
                                </div>

                                <div class="space-y-5">
                                    <div>
                                        <label class="form-label">Contraseña temporal<span class="form-required">*</span></label>
                                        <div class="input-with-icon">
                                            <span class="input-icon"><AppIcon name="lock" /></span>
                                            <input v-model="form.password" class="form-input input-with-prefix" type="password" required autocomplete="new-password" placeholder="Ingresa una contraseña temporal" />
                                        </div>
                                        <FormError :message="form.errors.password" />
                                    </div>

                                    <div>
                                        <label class="form-label">Confirmar contraseña<span class="form-required">*</span></label>
                                        <div class="input-with-icon">
                                            <span class="input-icon"><AppIcon name="check" /></span>
                                            <input v-model="form.password_confirmation" class="form-input input-with-prefix" type="password" required autocomplete="new-password" placeholder="Repite la contraseña temporal" />
                                        </div>
                                    </div>
                                </div>

                                <div class="notice-panel mt-5">
                                    <div class="notice-icon"><AppIcon name="shield" variant="illustrated" :size="42" /></div>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">Acceso seguro y controlado</p>
                                        <p class="mt-1 text-sm leading-6 text-slate-600">Verifica el correo antes de guardar. El usuario se creará con el rol seleccionado y quedará listo para iniciar sesión.</p>
                                    </div>
                                </div>
                            </section>

                            <section class="rounded-[1.5rem] border border-league-100 bg-gradient-to-br from-white to-league-50/60 p-5 shadow-sm sm:p-6">
                                <div class="flex items-start gap-4">
                                    <div class="section-icon"><AppIcon name="badge" variant="illustrated" :size="44" /></div>
                                    <div class="min-w-0 flex-1">
                                        <p class="form-section-eyebrow">Resumen</p>
                                        <h2 class="form-section-title">Asignación inicial</h2>
                                        <p class="mt-1 text-sm text-slate-500">Confirmación visual del rol y la acción que se realizará.</p>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-4">
                                    <div class="summary-row">
                                        <span class="summary-label">Usuario</span>
                                        <strong class="summary-value">{{ form.name || 'Pendiente de capturar' }}</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Correo</span>
                                        <strong class="summary-value">{{ form.email || 'Pendiente de capturar' }}</strong>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Rol inicial</span>
                                        <span v-if="selectedRole" class="inline-flex w-fit items-center gap-2 rounded-full bg-league-100 px-3 py-1.5 text-sm font-semibold text-league-700">
                                            <AppIcon name="badge" :size="15" />
                                            {{ selectedRole.name }}
                                        </span>
                                        <strong v-else class="summary-value text-slate-400">Sin seleccionar</strong>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">Al guardar, el sistema creará el usuario y asignará el rol seleccionado en la liga activa.</p>
                        <div class="flex flex-wrap justify-end gap-3">
                            <Link href="/liga/miembros" class="btn-secondary">Cancelar</Link>
                            <button class="btn-primary inline-flex min-w-44 items-center justify-center gap-2" type="submit" :disabled="form.processing">
                                <AppIcon name="check" :size="16" />
                                {{ form.processing ? 'Creando…' : 'Crear y asignar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </section>
    </AppLayout>
</template>

<style scoped>
@reference "../../../../css/app.css";

.page-hero {
    position: relative;
    background-image: linear-gradient(115deg, color-mix(in srgb, var(--color-league-900) 90%, #08182f), color-mix(in srgb, var(--color-league-600) 68%, transparent)), url('/images/public/league-stadium.jpg');
    background-position: center 42%;
    background-size: cover;
}

.page-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, rgba(7, 20, 39, .32), rgba(7, 20, 39, .08));
}

.hero-back-button {
    @apply inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-xl border border-white/25 bg-white/14 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20;
}

.create-user-shell {
    background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(248, 250, 252, .96));
}

.section-icon {
    @apply grid h-14 w-14 shrink-0 place-items-center rounded-2xl border border-white/80 bg-white shadow-sm;
}

.form-section-eyebrow {
    @apply text-[11px] font-extrabold uppercase tracking-[0.22em] text-league-600;
}

.form-section-title {
    @apply mt-1 text-xl font-bold text-slate-900;
}

.input-with-icon {
    position: relative;
}

.input-icon {
    @apply pointer-events-none absolute inset-y-0 left-4 flex items-center text-slate-400;
}

.input-with-prefix {
    padding-left: 3rem;
}

.notice-panel {
    @apply flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4;
}

.notice-icon {
    @apply mt-0.5 shrink-0;
}

.summary-row {
    @apply flex flex-col gap-1 rounded-2xl border border-white/80 bg-white/80 px-4 py-3 shadow-sm;
}

.summary-label {
    @apply text-[11px] font-bold uppercase tracking-[0.18em] text-slate-400;
}

.summary-value {
    @apply text-sm font-semibold text-slate-800 break-words;
}

@media (max-width: 640px) {
    .input-with-prefix {
        padding-left: 2.75rem;
    }
}
</style>
