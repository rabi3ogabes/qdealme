@foreach ($offers as $offer)
    <x-offer-card :offer="$offer" />
@endforeach
<template data-next-url="{{ $offers->nextPageUrl() }}"></template>
