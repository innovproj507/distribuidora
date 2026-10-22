<?php use App\Core\View; ?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación en dos pasos | Sistema POS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="h-full bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 font-[Inter]">
    <div class="min-h-full flex items-center justify-center py-12 px-4">
        <div class="max-w-sm w-full space-y-8">

            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center mb-4 shadow-lg shadow-indigo-500/30">
                    <i class="fas fa-shield-halved text-white text-2xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-white">Verificación en dos pasos</h2>
                <p class="mt-2 text-sm text-gray-400">Ingresa el código de tu aplicación de autenticación</p>
            </div>

            <?php if (!empty($flash)): ?>
                <?php foreach ($flash as $type => $messages): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="bg-red-500/10 border border-red-500/30 text-red-300 px-4 py-3 rounded-xl text-sm text-center">
                            <i class="fas fa-exclamation-circle mr-1"></i> <?= View::e($msg) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="bg-white/5 backdrop-blur-lg rounded-2xl border border-white/10 p-8 shadow-2xl">
                <form action="/auth/2fa/verificar" method="POST" class="space-y-6">
                    <?= View::csrf() ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2 text-center">
                            Código de 6 dígitos
                        </label>
                        <input type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                               maxlength="6" autocomplete="one-time-code" required autofocus
                               class="block w-full text-center text-3xl tracking-[0.5em] font-mono
                                      px-4 py-4 bg-white/5 border border-white/10 rounded-xl
                                      text-white placeholder-gray-600
                                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                               placeholder="000000">
                        <p class="mt-2 text-xs text-gray-500 text-center">
                            El código cambia cada 30 segundos
                        </p>
                    </div>

                    <button type="submit"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-xl font-semibold transition-all">
                        <i class="fas fa-check mr-2"></i> Verificar
                    </button>
                </form>
            </div>

            <div class="text-center">
                <a href="/login" class="text-sm text-gray-500 hover:text-gray-300 transition-colors">
                    <i class="fas fa-arrow-left mr-1"></i> Volver al inicio de sesión
                </a>
            </div>

        </div>
    </div>

    <script>
    // Auto-submit when 6 digits are entered
    document.querySelector('input[name="code"]').addEventListener('input', function () {
        if (this.value.replace(/\D/g, '').length === 6) {
            this.form.submit();
        }
    });
    </script>
</body>
</html>
