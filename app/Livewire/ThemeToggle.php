<?php
namespace App\Livewire;

use Livewire\Component;

class ThemeToggle extends Component
{
    public string $theme = 'system'; // 'light' | 'dark' | 'system'

    public function mount()
    {
        // 初始值由前端 JS 通过 Alpine dispatch 传入，默认 system
    }

    public function setTheme(string $theme)
    {
        $this->theme = $theme;
    }

    public function render()
    {
        return view('livewire.theme-toggle');
    }
}
