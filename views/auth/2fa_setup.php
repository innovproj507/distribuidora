<?php use App\Core\View; use App\Core\Auth; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="max-w-lg mx-auto space-y-6">
    <div class="text-center">
        <div class="mx-auto w-14 h-14 bg-indigo-100 rounded-2xl flex items-center justify-center mb-3">
            <i class="fas fa-shield-halved text-indigo-600 text-2xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-slate-800">Verificación en dos pasos</h2>
        <p class="text-slate-500 text-sm mt-1">
            Tu rol requiere 2FA. Sigue los pasos para activarlo.
        </p>
    </div>

    <?php if ($ya_activo): ?>
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-800">
        <i class="fas fa-check-circle mr-1"></i>
        La verificación en dos pasos ya está <strong>activa</strong> en tu cuenta.
        Si deseas resetearla, escanea el nuevo QR y confirma con un código.
    </div>
    <?php endif; ?>

    <!-- Paso 1: Escanear QR -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 space-y-4">
        <div class="flex items-center gap-3 mb-4">
            <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-bold flex items-center justify-center">1</span>
            <h3 class="font-semibold text-slate-700">Escanea el código QR</h3>
        </div>

        <p class="text-sm text-slate-600">
            Abre <strong>Google Authenticator</strong>, <strong>Authy</strong> u otra aplicación compatible
            y escanea el código QR:
        </p>

        <div class="flex justify-center py-2">
            <img src="<?= View::e($qr_image) ?>" alt="QR 2FA"
                 class="rounded-lg" style="width:220px;height:220px;">
        </div>

        <details class="text-xs">
            <summary class="cursor-pointer text-slate-400 hover:text-slate-600">
                ¿No puedes escanear? Ingresa el código manualmente
            </summary>
            <div class="mt-2 bg-slate-50 rounded-lg p-3 font-mono text-slate-700 tracking-widest text-center text-base select-all">
                <?= View::e($secret) ?>
            </div>
        </details>
    </div>

    <!-- Paso 2: Confirmar código -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6">
        <div class="flex items-center gap-3 mb-4">
            <span class="w-7 h-7 rounded-full bg-indigo-600 text-white text-sm font-bold flex items-center justify-center">2</span>
            <h3 class="font-semibold text-slate-700">Confirma con un código</h3>
        </div>

        <form method="POST" action="/auth/2fa/guardar-setup" class="space-y-4">
            <?= View::csrf() ?>
            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1">
                    Código de 6 dígitos de la app
                </label>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" autocomplete="one-time-code" required autofocus
                       placeholder="000000"
                       class="w-full text-center text-2xl tracking-[0.4em] font-mono rounded-xl border border-slate-200 px-4 py-3
                              focus:outline-none focus:ring-2 focus:ring-indigo-400">
            </div>
            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-semibold transition-all">
                <i class="fas fa-shield-halved mr-2"></i> Activar verificación en dos pasos
            </button>
        </form>
    </div>

    <?php if ($ya_activo && Auth::can('usuarios.gestionar')): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
        <h4 class="font-medium text-slate-700 mb-2 text-sm">Desactivar 2FA</h4>
        <form method="POST" action="/auth/2fa/desactivar"
              onsubmit="return confirm('¿Seguro que deseas desactivar la verificación en dos pasos?')">
            <?= View::csrf() ?>
            <input type="hidden" name="user_id" value="<?= Auth::id() ?>">
            <button type="submit"
                    class="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-sm font-medium">
                <i class="fas fa-shield-slash mr-1"></i> Desactivar 2FA de mi cuenta
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>


<?php View::endSection('content'); ?>
