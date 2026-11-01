<nav aria-label="Operasional" class="flex flex-wrap gap-4 text-sm text-indigo-700">
    @if(auth()->user()->hasRole('admin'))
        <a href="{{ route('admin.payouts') }}" wire:navigate>Honor instruktur</a>
        <a href="{{ route('admin.batch-operations') }}" wire:navigate>Antrean & salin batch</a>
        <a href="{{ route('admin.attendance') }}" wire:navigate>Absensi live</a>
        <a href="{{ route('admin.batches') }}" wire:navigate>Konfigurasi batch</a>
    @elseif(auth()->user()->hasRole('instructor'))
        <a href="{{ route('instructor.payouts') }}" wire:navigate>Honor saya</a>
        <a href="{{ route('instructor.attendance') }}" wire:navigate>Absensi live</a>
    @else
        <a href="{{ route('student.waitlist') }}" wire:navigate>Antrean saya</a>
        <a href="{{ route('student.browse') }}" wire:navigate>Jelajahi program</a>
        <a href="{{ route('student.my-courses') }}" wire:navigate>Kelas saya</a>
    @endif
</nav>
@if(session('success'))
    <div role="status" class="rounded-lg bg-green-50 p-4 text-green-800">{{ session('success') }}</div>
@endif
@if($errors->any())
    <ul role="alert" class="list-inside list-disc rounded-lg bg-red-50 p-4 text-red-800">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
@endif
