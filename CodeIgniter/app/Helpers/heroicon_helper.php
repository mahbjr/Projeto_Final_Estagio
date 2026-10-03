<?php

if (!function_exists('heroicon')) {
    /**
     * Renderiza o SVG de um Heroicon com classes CSS personalizadas.
     *
     * @param string $name  Nome do ícone (ex: 'user', 'lock-closed', 'envelope', 'eye')
     * @param string $style Estilo/tamanho (ex: 'outline', 'solid', '24/outline', '20/solid', '16/solid', 'mini', 'micro')
     * @param string $class Classes CSS aplicadas à tag <svg> (ex: 'size-6', 'w-5 h-5 text-gray-500')
     * @return string Código SVG inline pronto para exibição ou string vazia se não encontrado
     */
    function heroicon(string $name, string $style = 'outline', string $class = 'size-6'): string
    {
        $baseDir = ROOTPATH . 'vendor/evelution87/heroicons/';

        // Mapeia estilos convencionais para a estrutura do Heroicons v2
        $mappedStyle = match ($style) {
            'outline' => '24/outline',
            'solid'   => '24/solid',
            'mini'    => '20/solid',
            'micro'   => '16/solid',
            default   => $style,
        };

        // Ordem de busca de caminhos suportados
        $candidates = [
            $baseDir . "optimized/{$mappedStyle}/{$name}.svg",
            $baseDir . "src/{$mappedStyle}/{$name}.svg",
            $baseDir . "optimized/{$style}/{$name}.svg",
            $baseDir . "src/{$style}/{$name}.svg",
        ];

        $path = '';
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                $path = $candidate;
                break;
            }
        }

        if ($path === '') {
            return '';
        }

        $svg = file_get_contents($path);

        // Se o SVG já contiver o atributo class, substitui; caso contrário, insere class="..."
        if (preg_match('/<svg[^>]*\bclass="([^"]*)"/', $svg)) {
            return preg_replace('/<svg([^>]*)\bclass="[^"]*"/', '<svg$1class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"', $svg, 1);
        }

        return str_replace('<svg', '<svg class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"', $svg);
    }
}
