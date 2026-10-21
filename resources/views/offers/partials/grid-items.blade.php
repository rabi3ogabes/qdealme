@php $today = local_today(current_city()); @endphp
@foreach ($flyers as $flyer)
    <x-flyer-card :flyer="$flyer" :today="$today" />
@endforeach
<template data-next-url="{{ $flyers->nextPageUrl() }}"></template>
