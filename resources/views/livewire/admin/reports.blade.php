<?php
use Livewire\Volt\Component;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['lazy' => true])] class extends Component {

    public function placeholder()
    {
        return <<<'HTML'
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 animate-pulse">
                    <div class="h-6 bg-slate-200 rounded w-1/4 mb-4"></div>
                    <div class="h-32 bg-slate-100 rounded w-full"></div>
                </div>
            </div>
        </div>
        HTML;
    }
};
?>

<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
            Halaman ini belum ada isinya (Placeholder).
        </div>
    </div>
</div>
