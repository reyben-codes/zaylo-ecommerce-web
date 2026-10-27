{{-- Proof photos of a returned item: the rider's at pickup and the hub's on arrival. Expects $case. --}}
@php $labels = ['pickup' => 'Picked-up photo', 'hub' => 'At-hub photo']; @endphp
@foreach($case->returnPhotos() as $kind => $path)
    @include('logistics.operations.partials.proof-photo', [
        'url' => route('return-photo', [$case, $kind]),
        'label' => $labels[$kind],
        'alt' => $labels[$kind].' of return '.$case->return_tracking_number,
    ])
@endforeach
