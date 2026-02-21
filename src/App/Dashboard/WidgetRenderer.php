<?php

namespace App\Dashboard;

class WidgetRenderer
{
    private array $enabledWidgets;

    public function __construct(array $settings)
    {
        // Default widgets if none configured
        $defaultOrder = ['quick_links', 'net_worth', 'recent_transactions'];
        $this->enabledWidgets = $settings['dashboard_widgets'] ?? $defaultOrder;
    }

    public function renderAll(): void
    {
        echo '<div class="dashboard-grid">';
        foreach ($this->enabledWidgets as $widgetName) {
            $this->renderWidget($widgetName);
        }
        echo '</div>';
    }

    private function renderWidget(string $name): void
    {
        $path = __DIR__ . '/../../Views/Widgets/' . $name . '.php';
        if (file_exists($path)) {
            include $path;
        } else {
            echo "<div class='widget'>Widget '$name' not found.</div>";
        }
    }
}
