<x-layouts.app :seo="$seo">
    <article class="container-x max-w-3xl py-12">
        <h1 class="text-h1 font-semibold">{{ __('pages.terms.title') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('pages.terms.updated', ['date' => fmt_date(\Carbon\CarbonImmutable::create(2026, 10, 19), 'j F Y')]) }}</p>
        <div class="prose-brand mt-8">
            @if (app()->getLocale() === 'ar')
                <p>باستخدامك {{ brand() }} فإنك توافق على هذه الشروط.</p>
                <h2>المحتوى</h2>
                <p>النشرات والعروض مقدّمة من المتاجر وبإذنها. يبذل فريقنا جهده للتحقق من الأسعار الموسومة بـ«موثّق»، لكن في حال وجود اختلاف يُعتمد سعر المتجر في الفرع.</p>
                <h2>الاستخدام المسموح</h2>
                <p>الموقع مخصّص للاستخدام الشخصي. يُمنع نسخ المحتوى أو جمعه آلياً أو إعادة نشره دون إذن كتابي.</p>
                <h2>الحسابات</h2>
                <p>أنت مسؤول عن الحفاظ على سرية كلمة مرورك. يحق لنا إيقاف الحسابات التي تسيء استخدام الخدمة.</p>
                <h2>القانون المطبق</h2>
                <p>تخضع هذه الشروط لقوانين دولة قطر.</p>
            @else
                <p>By using {{ brand() }} you agree to these terms.</p>
                <h2>Content</h2>
                <p>Flyers and offers are provided by retailers, with their permission. Our team works hard to check every price marked “verified”, but if there is any difference, the store's in-branch price applies.</p>
                <h2>Permitted use</h2>
                <p>The site is for personal use. Copying, automated collection or republishing of content without written permission is not allowed.</p>
                <h2>Accounts</h2>
                <p>You are responsible for keeping your password secure. We may suspend accounts that abuse the service.</p>
                <h2>Governing law</h2>
                <p>These terms are governed by the laws of the State of Qatar.</p>
            @endif
        </div>
    </article>
</x-layouts.app>
