<?php

namespace App\Livewire;

use Livewire\Component;

class TestComponent extends Component
{
    public string $message = 'Livewire operativo';

    public int $counter = 0;

    public function increment(): void
    {
        $this->counter++;
    }

    public function render()
    {
        return view('livewire.test-component');
    }
}
