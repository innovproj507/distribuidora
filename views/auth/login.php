<?php use App\Core\View; ?>
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Distribuidora Arango</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="h-full bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 font-[Inter]">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Logo -->
            <div class="text-center">
                <div class="mx-auto w-16 h-16 bg-blue-600 rounded-2xl flex items-center justify-center mb-4 shadow-lg shadow-blue-500/30">
                    <i class="fas fa-store text-white text-2xl"></i>
                </div>
                <h2 class="text-3xl font-bold text-white">Distribuidora Arango</h2>
                <p class="mt-2 text-sm text-gray-400">Punto de Venta e Inventario</p>
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

            <!-- Form -->
            <div class="bg-white/5 backdrop-blur-lg rounded-2xl border border-white/10 p-8 shadow-2xl">
                <form action="/login" method="POST" class="space-y-5">
                    <?= View::csrf() ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Usuario</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500"><i class="fas fa-user text-sm"></i></span>
                            <input type="text" name="username" required autofocus
                                class="block w-full pl-10 pr-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500-500 focus:border-transparent"
                                placeholder="Ingrese su usuario">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Contraseña</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-500"><i class="fas fa-lock text-sm"></i></span>
                            <input type="password" name="password" id="password" required
                                class="block w-full pl-10 pr-10 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500-500 focus:border-transparent"
                                placeholder="Ingrese su contraseña">
                            <button type="button" onclick="togglePassword()"
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-300 transition-colors">
                                <i class="fas fa-eye text-sm" id="eye-icon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl font-semibold transition-all duration-200 hover:shadow-lg hover:shadow-blue-500/25">
                        <i class="fas fa-sign-in-alt mr-2"></i> Iniciar Sesión
                    </button>
                </form>
            </div>

            <p class="text-center text-sm text-gray-500">
                Distribuidora Arango &copy; <?= date('Y') ?>
                <span class="inline-block ml-2 text-[11px] text-gray-600 bg-white/10 border border-white/10 rounded-full px-2 py-0.5 font-mono">v<?= APP_VERSION ?></span>
            </p>
        </div>
    </div>
<script>
function togglePassword() {
    const input = document.getElementById('password');
    const icon  = document.getElementById('eye-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
</body>
</html>
