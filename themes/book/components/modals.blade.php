<!-- Модалка зв'язку -->
<div class="modal" id="contactModal" aria-hidden="true">
  <div class="modal__overlay" data-close></div>

  <div class="modal__content" role="dialog" aria-modal="true">
    <button class="modal__close" type="button" aria-label="Закрити">✕</button>

    <h2 class="modal__title">Зв’язатися з нами</h2>

    <form class="modal__form">
      <input type="text" name="name" placeholder="Ваше ім’я" required />
      <input type="tel" name="phone" placeholder="Ваш телефон" required />
      <input type="email" name="email" placeholder="Ваш email" />
      <textarea name="message" placeholder="Ваше повідомлення" required></textarea>

      <button type="submit" class="modal__btn button__buy button--shine">Надіслати</button>
    </form>

    <div class="modal__decor">

        <span class="modal__title-vertical modal__title-vertical__left">
          <span class="modal__title-vertical-inner">
            <span>АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.</span>
          </span>
        </span>

        <span class="modal__title-vertical modal__title-vertical__right ">
          <span class="modal__title-vertical-inner">
            <span>АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.</span>
          </span>
        </span>


      </div>
  </div>
</div>

<!-- Модалка покупки -->
<div class="modal" id="buyModal" aria-hidden="true">
  <div class="modal__overlay" data-close></div>

  <div class="modal__content" role="dialog" aria-modal="true">
    <button class="modal__close" type="button" aria-label="Закрити">✕</button>

    <h2 class="modal__title">Замовити книгу</h2>

    <form class="modal__form" id="buyForm">
      <input type="text" name="name" placeholder="Ваше ім’я" required />
      <input type="tel" name="phone" placeholder="Ваш телефон" required />
      <input type="email" name="email" placeholder="Ваш email" required />

      <p class="modal__note">
        *На вказану пошту прийде лист із купленою книгою.
      </p>

      <div class="modal__actions">
        <button type="button" class="modal__btn modal__btn--secondary" data-close>
          Скасувати
        </button>

        <button type="submit" class="modal__btn button__buy button--shine">
          Перейти до оплати
        </button>
      </div>
    </form>

    <div class="modal__decor">

        <span class="modal__title-vertical modal__title-vertical__left">
          <span class="modal__title-vertical-inner">
            <span>АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.</span>
          </span>
        </span>

        <span class="modal__title-vertical modal__title-vertical__right ">
          <span class="modal__title-vertical-inner">
            <span>АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ. УКРАЇНА. АЛЬМАНАХ. ХЛІБ.
              УКРАЇНА.</span>
          </span>
        </span>


      </div>
  </div>
</div>
<div class="cursor"></div>