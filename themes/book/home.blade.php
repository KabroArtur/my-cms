{{-- CMS_TEMPLATE: home|Главная страница --}}
{{-- CMS_TEMPLATE_DESCRIPTION: Заготовка страницы книги с обложкой, аннотацией, основным текстом и инфоблоком. --}}
<section class="hero" id="top">
      <div class="hero__intro">
  <img src="{{ theme_asset('assets/images/banners/14.webp') }}" alt="Паляниця" width="900" height="900">
      </div>
      <div class="hero__decor">

        <span class="hero__title-vertical hero__title-vertical__left ">
          <span class="hero__title-vertical-inner">
            <span>АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.</span>
          </span>
        </span>

        <span class="hero__title-vertical hero__title-vertical__right ">
          <span class="hero__title-vertical-inner">
            <span>АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.</span>
          </span>
        </span>


      </div>
      <div class="container">


        <div class="hero__inner">
          <h1 class="hero__title ">
            Хліб — це все життя великого народу.
          </h1>

          <div class="hero__wrapp">
            <p class="hero__subtitle">
              Перший двомовний альманах про український хліб — від зерна до паляниці, від Голодомору до сучасних
              трендів.
              60 сторінок економіки, культури, рецептів та історій людей, які печуть хліб попри все.
            </p>

            <button type="button" class="hero__cta button__buy button--shine" aria-label="buy">
              ЗАМОВИТИ ДРУКОВАНИЙ ПРИМІРНИК
            </button>
          </div>

        </div>
      </div>
    </section>

    <section class="about" id="about">
      <div class="container pause-position">

        <div class="about__grid">

          <div class="about__content _anim__items _anim__stop">
            <h2 class="about__title">Це не просто книга про хліб.<br> Це нарис про український народ.</h2>

            <div class="about__text">
              <p>
                <span>«bread. ukraine. almanac»</span> — це погляд на філософію хліба як щоденного супутника в
                економіці, культурі та побуті українців. Впродовж століть ми вирощували пшеницю й жито, оспівували хліб
                у піснях, казках і живописі. І сьогодні дух минулих поколінь можна відчути у кожній сучасній паляниці.
              </p>
            </div>
          </div>

          <div class="about__visual _anim__items _anim__stop">


      <img src="{{ theme_asset('assets/images/banners/almanah-500.webp') }}" srcset="
    {{ theme_asset('assets/images/banners/almanah-500.webp') }} 500w,
    {{ theme_asset('assets/images/banners/almanah-768.webp') }} 768w" sizes="300px" alt="Альманах.Хліб.Україна." width="400" height="400"
              loading="lazy">

          </div>

        </div>
      </div>
    </section>

    <section class="gallery">
      <div class="container">
        <h2 class="gallery__title-vertical _anim__items _anim__stop">Розвороти альманаху</h2>

        <div class="gallery__grid">
          <figure class="gallery__item gallery__item-1 _anim__items _anim__stop" data-title="Паляниця — слово, яке об'єднало націю.">
            <div class="gallery__media">
            <img src="{{ theme_asset('assets/images/banners/01-500.webp') }}" srcset="
          {{ theme_asset('assets/images/banners/01-500.webp') }} 500w,
          {{ theme_asset('assets/images/banners/01-768.webp') }} 768w" sizes="500px" alt="Паляниця" width="500" height="500" loading="lazy">
</div>
          </figure>
          <figure class="gallery__item gallery__item-2 _anim__items _anim__stop" data-title="Хліб — це поезія.">
            <div class="gallery__media">
            <img src="{{ theme_asset('assets/images/banners/02-500.webp') }}" srcset="
          {{ theme_asset('assets/images/banners/02-500.webp') }} 500w,
          {{ theme_asset('assets/images/banners/02-768.webp') }} 768w" sizes="500px" alt="Поезія про хліб" width="500" height="500" loading="lazy">
          </div>
      </figure>
          <figure class="gallery__item gallery__item-3 _anim__items _anim__stop" data-title="Від зерна до паляниці">
                <div class="gallery__media">
            <img src="{{ theme_asset('assets/images/banners/03-500.webp') }}" srcset="
          {{ theme_asset('assets/images/banners/03-500.webp') }} 500w,
          {{ theme_asset('assets/images/banners/03-768.webp') }} 768w" sizes="500px" alt="Пшениця" width="500" height="500" loading="lazy">
        </div>
          </figure>
          <figure class="gallery__item gallery__item-4 _anim__items _anim__stop" data-title="Щодня українські пекарі дарують мільйонам надію. ">
            <div class="gallery__media">
            <img src="{{ theme_asset('assets/images/banners/09-500.webp') }}" srcset="
          {{ theme_asset('assets/images/banners/09-500.webp') }} 500w,
          {{ theme_asset('assets/images/banners/09-768.webp') }} 768w" sizes="500px" alt="Український хліб" width="500" height="500" loading="lazy">
          </div>
      </figure>
        </div>
      </div>

    </section>

    <section class="themes" id="themes">
      <div class="container">

        <h2 class="themes__title-vertical _anim__items _anim__stop">ЩО ВСЕРЕДЕНІ</h2>
        <h3 class="themes__title _anim__items _anim__stop">Кожен розділ — окрема грань хліба</h3>

        <div class="themes__grid">
          <article class="themes__item themes__item-1 _anim__items _anim__stop">
            <span class="themes__label">Хліб. Економіка</span>
            <p class="themes__name">Дані про 13,6 млн домогосподарств-споживачів, 66+ кг хлібобулочних на рік, динаміка
              виробництва та експорту пшениці 2018–2024.</p>
          </article>

          <article class="themes__item themes__item-2 _anim__items _anim__stop">
            <span class="themes__label">Хліб. Війна</span>
            <p class="themes__name">Блокада портів, зерновий коридор, зруйновані підприємства та історія відновлення
              CHANTA. 14 підприємств, що постраждали від агресії.</p>
          </article>

          <article class="themes__item themes__item-3 _anim__items _anim__stop">
            <span class="themes__label">Хліб. Зброя</span>
            <p class="themes__name">Паляниця як слово-ідентифікатор українців навесні 2022 року.</p>
          </article>



          <article class="themes__item themes__item-4 _anim__items _anim__stop">
            <span class="themes__label">Хліб. Промисловість</span>
            <p class="themes__name">Професійні рецептури паляниці, гречаного хліба та здоби з технологічними параметрами
              Lesaffre Ukraine.</p>
          </article>

          <article class="themes__item themes__item-5 _anim__items _anim__stop">
            <span class="themes__label">Хліб. Розмова</span>
            <p class="themes__name">Ексклюзивне інтерв'ю зі Світланою Шинкаренко (Lesaffre Ukraine) про розвиток
              українського хлібопечення, сучасні тренди та повернення до традицій.</p>
          </article>

          <article class="themes__item themes__item-6 _anim__items _anim__stop">
            <span class="themes__label">Хліб. Тренди</span>
            <p class="themes__name">Хліб як гастрономічний простір, food on the go, здорове харчування, локальна
              ідентичність та відповідальне виробництво.</p>
          </article>

          <article class="themes__item themes__item-7 _anim__items _anim__stop">
            <span class="themes__label">Хліб. Культура</span>
            <p class="themes__name">Кіноавангард, поезія Тичини, фотопроєкт «Космос українського хліба», народні казки
              та приказки, музей хліба у Києві.</p>
          </article>

          <article class="themes__item themes__item-8 _anim__items _anim__stop">
            <span class="themes__label">Хліб. 1932–33</span>
            <p class="themes__name">Голодомор як акт геноциду та свідчення очевидців.</p>
          </article>
        </div>
      </div>
      </div>
    </section>


    <section class="history" id="history">
      <div class="container pause-position ">
        <div class="history__inner _anim__items _anim__stop">
          <h2 class="history__years">1932–1933</h2>
          <p class="history__text">
            Памʼять про Голодомор має практично кожна українська родина.
          </p>
        </div>
        <span class="pause-vertical__text _anim__items _anim__stop">
          ПАМʼЯТЬ І ЧАС
        </span>
      </div>

    </section>

    <section class="numbers" id="numbers">
      <div class="container">


        <h2 class="numbers__title _anim__items _anim__stop">Хліб у цифрах</h2>


        <div class="numbers__list">

          <div class="numbers__item numbers__item-1 _anim__items _anim__stop">
            <div class="numbers__value">13,6 млн</div>
            <p class="numbers__desc">
              домогосподарств в Україні споживають хлібобулочні вироби
            </p>
          </div>

          <div class="numbers__item numbers__item-2 _anim__items _anim__stop">
            <div class="numbers__value">66+ кг</div>
            <p class="numbers__desc">
              хлібобулочних виробів купує середнє домогосподарство на рік — це близько 166 буханців
            </p>
          </div>

          <div class="numbers__item numbers__item-3 _anim__items _anim__stop">
            <div class="numbers__value">3+ млн</div>
            <p class="numbers__desc">
              тонн річна потужність українських млинів, експорт у 50+ країн
            </p>
          </div>

          <div class="numbers__item numbers__item-4 _anim__items _anim__stop">
            <div class="numbers__value">300 000</div>
            <p class="numbers__desc">
              хлібин безкоштовно передано інклюзивною пекарнею Good Bread
            </p>
          </div>

        </div>

      </div>
    </section>

    <section class="credits" id="autor">
      <div class="container">
        <h2 class="credits__title-vertical _anim__items _anim__stop">ХТО СТВОРИВ</h2>
        <h3 class="credits__title _anim__items _anim__stop">Створено професіоналами галузі</h3>
        <div class="credits__item _anim__items _anim__stop">

          <h4>Автори</h4>
          <p>
            Олена Лапшова, Марина Скрипченко,
            Тетяна Пашковська, Іван Кушпель,
            Олег Киселиця
          </p>
        </div>

        <div class="credits__item _anim__items _anim__stop">
          <h4>За підтримки</h4>
          <p>
            Тараненко Олександр — Президент Всеукраїнської асоціації пекарів України
          </p>
          <p>
            Рибчинський Родіон — Директор Спілки «Борошномели України»
          </p>
          <p>
            Ткаченко Святослав — Власник і CEO Agro Marketing Agency
          </p>
        </div>
      </div>
    </section>

    <section class="gallery">
      <div class="container">
        <h2 class="gallery__title-vertical _anim__items _anim__stop">Розвороти альманаху</h2>

        <div class="gallery__grid">
          <figure class="gallery__item gallery__item-1 _anim__items _anim__stop">
            <img src="{{ theme_asset('assets/images/banners/05-500.webp') }}" srcset="
        {{ theme_asset('assets/images/banners/05-500.webp') }} 500w,
        {{ theme_asset('assets/images/banners/05-768.webp') }} 768w" sizes="500px" alt="Дівчинка и і паски" width="500" height="500"
              loading="lazy">

          </figure>
          <figure class="gallery__item gallery__item-2 _anim__items _anim__stop">
            <img src="{{ theme_asset('assets/images/banners/06-500.webp') }}" srcset="
        {{ theme_asset('assets/images/banners/06-500.webp') }} 500w,
        {{ theme_asset('assets/images/banners/06-768.webp') }} 768w" sizes="500px" alt="Пампушка з часником" width="500" height="500"
              loading="lazy">
          </figure>
          <figure class="gallery__item gallery__item-3 _anim__items _anim__stop">
            <img src="{{ theme_asset('assets/images/banners/07-500.webp') }}" srcset="
        {{ theme_asset('assets/images/banners/07-500.webp') }} 500w,
        {{ theme_asset('assets/images/banners/07-768.webp') }} 768w" sizes="500px" alt="Каравай" width="500" height="500" loading="lazy">
          </figure>
          <figure class="gallery__item gallery__item-4 _anim__items _anim__stop">
            <img src="{{ theme_asset('assets/images/banners/08-500.webp') }}" srcset="
        {{ theme_asset('assets/images/banners/08-500.webp') }} 500w,
        {{ theme_asset('assets/images/banners/08-768.webp') }} 768w" sizes="500px" alt="Дідух" width="500" height="500" loading="lazy">
          </figure>
        </div>
      </div>

    </section>

    <section class="almanac-fragment">
      <div class="fragment-base">
        Хліб казка Bread is a fairy tale Хліб майбутнє Bread is the future Хліб пісня Bread is a song Хліб приказка
        Bread is a saying Хліб фільм Bread is a movie Хліб свято Bread is a holiday Хліб музей Bread is a Museum Хліб
        війна Bread is a war Хліб розмова Bread is a conversation Хліб промисловість Bread is an industry Хліб економіка
        Bread is an economy Хліб поезія Bread is poetry Хліб рідна Bread is native Хліб рідіна Bread is liquid Хліб
        мистецтво Bread is art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб емпатія Bread is empathy Хліб пошта
        Bread is mail Хліб AI bread Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread art Хліб
        1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread song Хліб приказка Bread proverb Хліб saying Bread
        свято Bread holiday Хліб економіка Bread economy Хліб промисловість Bread industry Хліб музей Bread museum Хліб
        фільм Bread movie Хліб поезія Bread poetry Хліб майбутнє Bread future Хліб казка Bread fairy tale Хліб майбутьнє
        Bread saying Хліб свято Bread holiday Хліб емпатія Bread empathy Хліб пошта Bread mail Хліб мистецтво Bread art
        Хліб 1932-33 Bread 1932-33 Хліб війна Bread war Хліб розмова Bread conversation Хліб щоденна Bread everyday Хліб
        дом Bread home Хліб зброя Bread weapon Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread art
        Хліб поезія Bread poetry Хліб війна 1932-33 Хліб розмова Хліб AI bread Хліб рідна Bread рідіна Хліб мистецтво
        Bread art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread song Хліб приказка Bread proverb Хліб
        saying Bread свято Bread holiday Хліб економіка Bread economy Хліб промисловість Bread industry Хліб музей Bread
        museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб майбутнє Bread future Хліб казка Bread fairy tale
        Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб емпатія Bread empathy Хліб пошта Bread mail Хліб
        мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна Bread war Хліб розмова Bread conversation Хліб щоденна
        Bread everyday Хліб дом Bread home Хліб зброя Bread weapon Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб
        мистецтво Bread art Хліб поезія Bread poetry Хліб війна розмова Хліб AI bread Хліб 1932-33 Хліб розмова Хліб AI
        bread Хліб рідна Bread рідіна Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня
        Bread song Хліб приказка Bread proverb Хліб saying Bread свято Bread holiday Хліб економіка Bread economy Хліб
        промисловість Bread industry Хліб музей Bread museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб
        майбутнє Bread future Хліб казка Bread fairy tale Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб
        емпатія Bread empathy Хліб пошта Bread mail Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна Bread
        war Хліб розмова Bread conversation Хліб щоденна Bread everyday Хліб дом Bread home Хліб зброя Bread weapon Хліб
        рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread art Хліб поезія Bread poetry
        Хліб казка Bread is a fairy tale Хліб майбутнє Bread is the future Хліб пісня Bread is a song Хліб приказка
        Bread is a saying Хліб фільм Bread is a movie Хліб свято Bread is a holiday Хліб музей Bread is a Museum Хліб
        війна Bread is a war Хліб розмова Bread is a conversation Хліб промисловість Bread is an industry Хліб економіка
        Bread is an economy Хліб поезія Bread is poetry Хліб рідна Bread is native Хліб рідіна Bread is liquid Хліб
        мистецтво Bread is art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб емпатія Bread is empathy Хліб пошта
        Bread is mail Хліб AI bread Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread art Хліб
        1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread song Хліб приказка Bread proverb Хліб saying Bread
        свято Bread holiday Хліб економіка Bread economy Хліб промисловість Bread industry Хліб музей Bread museum Хліб
        фільм Bread movie Хліб поезія Bread poetry Хліб майбутнє Bread future Хліб казка Bread fairy tale Хліб майбутьнє
        Bread saying Хліб свято Bread holiday Хліб емпатія Bread empathy Хліб пошта Bread mail Хліб мистецтво Bread art
        Хліб 1932-33 Bread 1932-33 Хліб війна Bread war Хліб розмова Bread conversation Хліб щоденна Bread everyday Хліб
        дом Bread home Хліб зброя Bread weapon Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread art
        Хліб поезія Bread poetry Хліб війна 1932-33 Хліб розмова Хліб AI bread Хліб рідна Bread рідіна Хліб мистецтво
        Bread art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread song Хліб приказка Bread proverb Хліб
        saying Bread свято Bread holiday Хліб економіка Bread economy Хліб промисловість Bread industry Хліб музей Bread
        museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб майбутнє Bread future Хліб казка Bread fairy tale
        Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб емпатія Bread empathy Хліб пошта Bread mail Хліб
        мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна Bread war Хліб розмова Bread conversation Хліб щоденна
        Bread everyday Хліб дом Bread home Хліб зброя Bread weapon Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб
        мистецтво Bread art Хліб поезія Bread poetry Хліб війна розмова Хліб AI bread Хліб 1932-33 Хліб розмова Хліб AI
        bread Хліб рідна Bread рідіна Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня
        Bread song Хліб приказка Bread proverb Хліб saying Bread свято Bread holiday Хліб економіка Bread economy Хліб
        промисловість Bread industry Хліб музей Bread museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб
        майбутнє Bread future Хліб казка Bread fairy tale Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб
        емпатія Bread empathy Хліб пошта Bread mail Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна Bread
        war Хліб розмова Bread conversation Хліб щоденна Bread everyday Хліб дом Bread home Хліб зброя Bread weapon Хліб
        рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread art Хліб поезія Bread poetry
      </div>

      <div class="fragment-magnifier">
        <div class="fragment-zoom-text">
          Хліб казка Bread is a fairy tale Хліб майбутнє Bread is the future Хліб пісня Bread is a song Хліб приказка
          Bread is a saying Хліб фільм Bread is a movie Хліб свято Bread is a holiday Хліб музей Bread is a Museum Хліб
          війна Bread is a war Хліб розмова Bread is a conversation Хліб промисловість Bread is an industry Хліб
          економіка Bread is an economy Хліб поезія Bread is poetry Хліб рідна Bread is native Хліб рідіна Bread is
          liquid Хліб мистецтво Bread is art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб емпатія Bread is empathy
          Хліб пошта Bread is mail Хліб AI bread Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread
          art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread song Хліб приказка Bread proverb Хліб
          saying Bread свято Bread holiday Хліб економіка Bread economy Хліб промисловість Bread industry Хліб музей
          Bread museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб майбутнє Bread future Хліб казка Bread fairy
          tale Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб емпатія Bread empathy Хліб пошта Bread mail
          Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна Bread war Хліб розмова Bread conversation Хліб
          щоденна Bread everyday Хліб дом Bread home Хліб зброя Bread weapon Хліб рідна Bread рідна Хліб рідіна Bread
          liquid Хліб мистецтво Bread art Хліб поезія Bread poetry Хліб війна 1932-33 Хліб розмова Хліб AI bread Хліб
          рідна Bread рідіна Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread
          song Хліб приказка Bread proverb Хліб saying Bread свято Bread holiday Хліб економіка Bread economy Хліб
          промисловість Bread industry Хліб музей Bread museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб
          майбутнє Bread future Хліб казка Bread fairy tale Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб
          емпатія Bread empathy Хліб пошта Bread mail Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна
          Bread war Хліб розмова Bread conversation Хліб щоденна Bread everyday Хліб дом Bread home Хліб зброя Bread
          weapon Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread art Хліб поезія Bread poetry Хліб
          війна розмова Хліб AI bread Хліб 1932-33 Хліб розмова Хліб AI bread Хліб рідна Bread рідіна Хліб мистецтво
          Bread art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread song Хліб приказка Bread proverb Хліб
          saying Bread свято Bread holiday Хліб економіка Bread economy Хліб промисловість Bread industry Хліб музей
          Bread museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб майбутнє Bread future Хліб казка Bread fairy
          tale Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб емпатія Bread empathy Хліб пошта Bread mail
          Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна Bread war Хліб розмова Bread conversation Хліб
          щоденна Bread everyday Хліб дом Bread home Хліб зброя Bread weapon Хліб рідна Bread рідна Хліб рідіна Bread
          liquid Хліб мистецтво Bread art Хліб поезія Bread poetry
          Хліб казка Bread is a fairy tale Хліб майбутнє Bread is the future Хліб пісня Bread is a song Хліб приказка
          Bread is a saying Хліб фільм Bread is a movie Хліб свято Bread is a holiday Хліб музей Bread is a Museum Хліб
          війна Bread is a war Хліб розмова Bread is a conversation Хліб промисловість Bread is an industry Хліб
          економіка Bread is an economy Хліб поезія Bread is poetry Хліб рідна Bread is native Хліб рідіна Bread is
          liquid Хліб мистецтво Bread is art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб емпатія Bread is empathy
          Хліб пошта Bread is mail Хліб AI bread Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread
          art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread song Хліб приказка Bread proverb Хліб
          saying Bread свято Bread holiday Хліб економіка Bread economy Хліб промисловість Bread industry Хліб музей
          Bread museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб майбутнє Bread future Хліб казка Bread fairy
          tale Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб емпатія Bread empathy Хліб пошта Bread mail
          Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна Bread war Хліб розмова Bread conversation Хліб
          щоденна Bread everyday Хліб дом Bread home Хліб зброя Bread weapon Хліб рідна Bread рідна Хліб рідіна Bread
          liquid Хліб мистецтво Bread art Хліб поезія Bread poetry Хліб війна 1932-33 Хліб розмова Хліб AI bread Хліб
          рідна Bread рідіна Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread
          song Хліб приказка Bread proverb Хліб saying Bread свято Bread holiday Хліб економіка Bread economy Хліб
          промисловість Bread industry Хліб музей Bread museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб
          майбутнє Bread future Хліб казка Bread fairy tale Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб
          емпатія Bread empathy Хліб пошта Bread mail Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна
          Bread war Хліб розмова Bread conversation Хліб щоденна Bread everyday Хліб дом Bread home Хліб зброя Bread
          weapon Хліб рідна Bread рідна Хліб рідіна Bread liquid Хліб мистецтво Bread art Хліб поезія Bread poetry Хліб
          війна розмова Хліб AI bread Хліб 1932-33 Хліб розмова Хліб AI bread Хліб рідна Bread рідіна Хліб мистецтво
          Bread art Хліб 1932-33 Bread 1932-33 Хліб.com Bread.com Хліб пісня Bread song Хліб приказка Bread proverb Хліб
          saying Bread свято Bread holiday Хліб економіка Bread economy Хліб промисловість Bread industry Хліб музей
          Bread museum Хліб фільм Bread movie Хліб поезія Bread poetry Хліб майбутнє Bread future Хліб казка Bread fairy
          tale Хліб майбутьнє Bread saying Хліб свято Bread holiday Хліб емпатія Bread empathy Хліб пошта Bread mail
          Хліб мистецтво Bread art Хліб 1932-33 Bread 1932-33 Хліб війна Bread war Хліб розмова Bread conversation Хліб
          щоденна Bread everyday Хліб дом Bread home Хліб зброя Bread weapon Хліб рідна Bread рідна Хліб рідіна Bread
          liquid Хліб мистецтво Bread art Хліб поезія Bread poetry
        </div>
      </div>
    </section>

    <section class="audience" id="for">
      <div class="container">


        <h2 class="audience__title _anim__items _anim__stop">Для кого цей альманах</h2>

        <ul class="audience__text">
     
            <li class="audience__text-1 _anim__items _anim__stop">
              <img src="{{ theme_asset('assets/images/icons/1.webp') }}" alt="Іконка пекаря" width="40" height="40" loading="lazy">
          <p>
            <span>Для пекарів і технологів</span>
            Професійні рецептури з технологічними параметрами, інсайти про тренди та інтерв'ю з лідерами галузі.
          </p>
            </li>
         
          <li class="audience__text-2 _anim__items _anim__stop">
            <img src="{{ theme_asset('assets/images/icons/2.webp') }}" alt="Іконка зерна" width="40" height="40" loading="lazy">
            <p>
            <span>Для борошномелів та зернопереробників</span>
            Карта млинів України, дані про потужності галузі та експортний потенціал борошна у 50+ країн.
          </p>
          </li>

          <li class="audience__text-3 _anim__items _anim__stop">
            <img src="{{ theme_asset('assets/images/icons/3.webp') }}" alt="Іконка агробизнесу" width="40" height="40" loading="lazy">
            <p>
            <span>Для керівників агробізнесу</span>
            Двомовне видання (UKR/EN) — презентує українську хлібопекарську традицію міжнародній аудиторії. Ідеально для
            виставок, форумів, ділових зустрічей.
          </p>
          </li>

          <li class="audience__text-4 _anim__items _anim__stop">
           <img src="{{ theme_asset('assets/images/icons/4.webp') }}" alt="Іконка партнерів" width="40" height="40" loading="lazy">
            <p>
            <span>Для міжнародних партнерів</span>
            Двомовне видання (UKR/EN) — презентує українську хлібопекарську традицію міжнародній аудиторії. Ідеально для
            виставок, форумів, ділових зустрічей.
          </p>
          </li>

          
        </ul>

      </div>
    </section>

    <section class="cta" id="buy">
      <div class="container">
        <div class="cta__inner">
          <h2 class="cta__title _anim__items _anim__stop">
            Нехай дух українського хліба наповнить вас натхненням.
          </h2>
          <div class="cta__wrapp _anim__items _anim__stop">
            <p class="cta__text ">
              Замовте друкований примірник альманаху — для себе, своєї команди <br> або як представницький подарунок
              партнерам та клієнтам.
            </p>

            <button type="button" class="cta__button button__buy button--shine" aria-label="contact">
              ЗВ`ЯЗАТИСЯ
            </button>
          </div>
        </div>
        <div class="cta__block">
          <img class="cta__img-1 _anim__items _anim__stop" src="{{ theme_asset('assets/images/banners/12.webp') }}" alt="Хліб" width="400"
            height="400" loading="lazy">
          <img class="cta__img-2 _anim__items _anim__stop" src="{{ theme_asset('assets/images/banners/18.webp') }}" alt="Альманах" width="400"
            height="400" loading="lazy">
        </div>

      </div>
    </section>