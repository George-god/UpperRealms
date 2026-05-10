<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  array<int, array{label: string, href?: string|null}>  $breadcrumbs
     */
    public function __construct(
        public array $breadcrumbs = [],
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
