<?php use App\Core\View; View::layout('app'); ?>
<?php View::section('content'); ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Lista de Roles -->
    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold text-gray-900">Roles del Sistema</h3>
            <form method="post" action="/configuracion/roles/crear" class="flex">
                <?= View::csrf() ?>
                <input type="text" name="nombre_rol" placeholder="Nuevo rol" class="w-full px-3 py-2 border rounded-lg text-sm mr-2" required>
                <button type="submit" class="bg-sky-500 hover:bg-sky-600 text-white font-medium py-2 px-4 rounded-lg flex items-center whitespace-nowrap shadow-sm shadow-sky-500/30">
                    <i class="fas fa-plus mr-1"></i> Crear
                </button>
            </form>
        </div>

        <div class="space-y-3">
            <!-- Mock roles for design parity -->
            <div class="border border-gray-100 rounded-xl p-4 bg-blue-50/30">
                <div class="flex justify-between items-center">
                    <h4 class="font-bold text-gray-900">Administrador</h4>
                    <span class="text-xs bg-blue-500 text-white px-2.5 py-1 rounded-full font-bold">
                        Pleno Acceso
                    </span>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span class="bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-md">Gestión Ventas</span>
                    <span class="bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-md">Configuración</span>
                    <span class="bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-md">Inventario</span>
                    <span class="text-xs text-gray-400">+all</span>
                </div>
            </div>

            <div class="border border-gray-100 rounded-xl p-4">
                <div class="flex justify-between items-center">
                    <h4 class="font-bold text-gray-900">Vendedor</h4>
                    <span class="text-xs bg-gray-400 text-white px-2.5 py-1 rounded-full font-bold">
                        Acceso Limitado
                    </span>
                </div>
                <div class="mt-2 flex flex-wrap gap-1.5">
                    <span class="bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-md">Crear Ventas</span>
                    <span class="bg-blue-500 text-white text-xs font-semibold px-2 py-1 rounded-md">Ver Productos</span>
                </div>
            </div>

            <p class="text-gray-400 text-center py-4 text-sm">Use el formulario para crear nuevos roles personalizados</p>
        </div>
    </div>

    <!-- Gestión de Permisos -->
    <div class="bg-white rounded-2xl shadow-sm p-6 border border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Asignar Permisos</h3>

        <form method="post" action="/configuracion/roles/permisos">
            <?= View::csrf() ?>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Seleccionar Rol</label>
                <select name="rol_id" class="w-full px-3 py-2 border rounded-lg text-sm" required>
                    <option value="">Selecciona un rol</option>
                    <option value="1">Administrador</option>
                    <option value="2">Vendedor</option>
                </select>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Permisos Disponibles</label>
                <div class="border border-gray-100 rounded-xl p-4 max-h-64 overflow-y-auto space-y-4">
                    <div>
                        <h5 class="font-bold text-gray-800 text-xs uppercase tracking-wider mb-2">Ventas</h5>
                        <div class="grid grid-cols-1 gap-2">
                            <label class="flex items-center text-sm cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" name="permisos[]" class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="ml-2 text-gray-700">Crear Venta</span>
                            </label>
                            <label class="flex items-center text-sm cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" name="permisos[]" class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="ml-2 text-gray-700">Anular Venta</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <h5 class="font-bold text-gray-800 text-xs uppercase tracking-wider mb-2">Inventario</h5>
                        <div class="grid grid-cols-1 gap-2">
                            <label class="flex items-center text-sm cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" name="permisos[]" class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="ml-2 text-gray-700">Gestión de Stock</span>
                            </label>
                            <label class="flex items-center text-sm cursor-pointer hover:bg-gray-50 p-1 rounded">
                                <input type="checkbox" name="permisos[]" class="w-4 h-4 text-blue-600 border-gray-300 rounded">
                                <span class="ml-2 text-gray-700">Editar Productos</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit" class="bg-sky-500 hover:bg-sky-600 text-white font-medium py-2 px-6 rounded-lg w-full transition shadow-sm shadow-sky-500/30">
                <i class="fas fa-save mr-2"></i> Actualizar Permisos
            </button>
        </form>
    </div>
</div>

<?php View::endSection('content'); ?>
