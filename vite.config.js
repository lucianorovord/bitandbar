import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/comida/registrar.js', 'resources/js/entrenamiento/registrar.js', 'resources/js/recetas/index.js', 'resources/js/inicio/inicio.js', 'resources/sass/inicio.scss', 'resources/sass/entrenamientos/entrenamiento.scss', 'resources/sass/comidas/comidas.scss', 'resources/sass/breeze-custom.scss'],
            refresh: true,
        }),
    ],
});
