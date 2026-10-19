<x-layouts.app :seo="$seo">
    <article class="container-x max-w-3xl py-12">
        <h1 class="text-h1 font-semibold">{{ __('pages.privacy.title') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('pages.privacy.updated', ['date' => fmt_date(\Carbon\CarbonImmutable::create(2026, 10, 19), 'j F Y')]) }}</p>
        <div class="prose-brand mt-8">
            @if (app()->getLocale() === 'ar')
                <p>نحترم خصوصيتك. توضّح هذه السياسة البيانات التي يجمعها {{ brand() }} وكيف نستخدمها ونحميها، وفقاً لقانون حماية خصوصية البيانات الشخصية في دولة قطر.</p>
                <h2>البيانات التي نجمعها</h2>
                <ul>
                    <li><strong>بيانات الحساب:</strong> الاسم والبريد الإلكتروني وكلمة المرور المشفّرة وتفضيلات اللغة والمدينة، فقط إذا أنشأت حساباً.</li>
                    <li><strong>تفضيلاتك:</strong> اللغة والمدينة ونمط الأرقام والمظهر، تُحفظ في ملفات تعريف ارتباط ضرورية لعمل الموقع.</li>
                    <li><strong>الرسائل والبلاغات:</strong> ما ترسله لنا عبر نموذج التواصل أو البلاغات. نحفظ بصمة مشفّرة لعنوان IP لمنع الإساءة، لا العنوان نفسه.</li>
                </ul>
                <h2>كيف نستخدم بياناتك</h2>
                <p>نستخدم بياناتك لتشغيل الموقع، وحفظ نشراتك ومتاجرك المتابَعة، والرد على رسائلك، وإرسال بريد العروض الأسبوعي فقط إذا وافقت عليه. لا نبيع بياناتك الشخصية لأي طرف.</p>
                <h2>ملفات تعريف الارتباط</h2>
                <p>نستخدم ملفات تعريف الارتباط الضرورية فقط لتذكّر الجلسة واللغة والمدينة. لا نستخدم أدوات تتبّع إعلانية.</p>
                <h2>حقوقك</h2>
                <p>يمكنك تعديل بياناتك أو حذف حسابك نهائياً في أي وقت من صفحة «حسابي». لأي طلب آخر راسلنا على <a href="mailto:{{ brand('support_email') }}">{{ brand('support_email') }}</a>.</p>
            @else
                <p>We respect your privacy. This policy explains what data {{ brand() }} collects, how we use it and how we protect it, in line with Qatar's Personal Data Privacy Protection Law.</p>
                <h2>What we collect</h2>
                <ul>
                    <li><strong>Account data:</strong> your name, email, hashed password and language and city preferences, only if you create an account.</li>
                    <li><strong>Preferences:</strong> language, city, number style and theme, stored in cookies that the site needs to work.</li>
                    <li><strong>Messages and reports:</strong> what you send us through the contact form or a report. We store a one-way hash of your IP address to prevent abuse, never the address itself.</li>
                </ul>
                <h2>How we use it</h2>
                <p>We use your data to run the site, keep your saved flyers and followed stores, reply to your messages and send the weekly deals email only if you opted in. We never sell personal data.</p>
                <h2>Cookies</h2>
                <p>We only use essential cookies to remember your session, language and city. We do not use advertising trackers.</p>
                <h2>Your rights</h2>
                <p>You can edit your details or permanently delete your account at any time from “My account”. For any other request email <a href="mailto:{{ brand('support_email') }}">{{ brand('support_email') }}</a>.</p>
            @endif
        </div>
    </article>
</x-layouts.app>
