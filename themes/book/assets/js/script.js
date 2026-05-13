document.addEventListener("DOMContentLoaded", () => {
  cloneHeaderToMobile();
  initScrollUp();
  initBurgerMenu();
  initActiveAnchors();
  initHeaderScrollState();
});


function cloneHeaderToMobile() {
  const headerLogo = document.querySelector(".header__logo");
  const mobileLogoContainer = document.querySelector(".mobile__logo");

  if (headerLogo && mobileLogoContainer) {
    mobileLogoContainer.appendChild(headerLogo.cloneNode(true));
  }

  const headerList = document.querySelector(".header__list");
  const navMenuContainer = document.querySelector(".nav__menu");

  if (headerList && navMenuContainer) {
    navMenuContainer.appendChild(headerList.cloneNode(true));
  }
}


function initScrollUp() {
  const scrollButton = document.querySelector(".footer__scrollup");
  if (!scrollButton) return;

  window.addEventListener("scroll", () => {
    scrollButton.classList.toggle("visible", window.scrollY > 400);
  });

  scrollButton.addEventListener("click", (e) => {
    e.preventDefault();
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
  });
}


let scrollPosition = 0;

function lockScroll() {
  scrollPosition = window.scrollY;

  document.body.style.position = "fixed";
  document.body.style.top = `-${scrollPosition}px`;
  document.body.style.left = "0";
  document.body.style.width = "100%";
}

function unlockScroll() {
  document.body.style.position = "";
  document.body.style.top = "";
  document.body.style.left = "";
  document.body.style.width = "";

  window.scrollTo(0, scrollPosition);
}


function initBurgerMenu() {
  const openMenuBtn = document.querySelector("[data-menu-open]");
  const closeMenuBtn = document.querySelector("[data-menu-close]");
  const mobileMenu = document.querySelector("[data-menu]");

  if (!openMenuBtn || !closeMenuBtn || !mobileMenu) return;

  openMenuBtn.addEventListener("click", () => {
    mobileMenu.classList.add("is-open");
    document.body.classList.add("backdrop__show");
    openMenuBtn.setAttribute("aria-expanded", "true");
    lockScroll();
  });

  closeMenuBtn.addEventListener("click", closeMenu);

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && mobileMenu.classList.contains("is-open")) {
      closeMenu();
    }
  });

  mobileMenu.addEventListener("click", (e) => {
    const link = e.target.closest('a[href^="#"]');
    if (!link) return;

    closeMenu();
  });

  function closeMenu() {
    mobileMenu.classList.remove("is-open");
    document.body.classList.remove("backdrop__show");
    openMenuBtn.setAttribute("aria-expanded", "false");
    unlockScroll();
  }
}


function initActiveAnchors() {
  const sections = document.querySelectorAll("section[id]");
  const navLinks = document.querySelectorAll(
    '.header__link[href^="#"], .nav__menu a[href^="#"]'
  );

  if (!sections.length || !navLinks.length) return;

  const observerOptions = {
    root: null,
    rootMargin: "-40% 0px -50% 0px",
    threshold: 0,
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;

      const id = entry.target.getAttribute("id");

      navLinks.forEach((link) => {
        const isActive = link.getAttribute("href") === `#${id}`;
        link.classList.toggle("current", isActive);

        if (isActive) {
          link.setAttribute("aria-current", "true");
        } else {
          link.removeAttribute("aria-current");
        }
      });
    });
  }, observerOptions);

  sections.forEach((section) => observer.observe(section));
}


function initHeaderScrollState() {
  const header = document.querySelector(".header");
  if (!header) return;

  window.addEventListener("scroll", () => {
    header.classList.toggle("is-scrolled", window.scrollY > 10);
  });
}



const fragment = document.querySelector('.almanac-fragment');
const base = document.querySelector('.fragment-base');
const magnifier = document.querySelector('.fragment-magnifier');
const zoomText = document.querySelector('.fragment-zoom-text');

fragment.addEventListener('mousemove', (e) => {
  const rect = fragment.getBoundingClientRect();
  const x = e.clientX - rect.left;
  const y = e.clientY - rect.top;

  magnifier.style.display = 'block';
  magnifier.style.left = `${x}px`;
  magnifier.style.top = `${y}px`;

  zoomText.style.left = `-${x}px`;
  zoomText.style.top = `-${y}px`;

  base.style.maskImage =
    `radial-gradient(circle 60px at ${x}px ${y}px, transparent 99%, black 100%)`;
  base.style.webkitMaskImage =
    `radial-gradient(circle 60px at ${x}px ${y}px, transparent 99%, black 100%)`;
});

fragment.addEventListener('mouseleave', () => {
  magnifier.style.display = 'none';
  base.style.maskImage = '';
  base.style.webkitMaskImage = '';
});


// modal
document.addEventListener("DOMContentLoaded", function () {
  const buyModal = document.getElementById("buyModal");
  const contactModal = document.getElementById("contactModal");

  const buyTriggers = document.querySelectorAll('[aria-label="buy"]');
  const contactTriggers = document.querySelectorAll('[aria-label="contact"]');

  let lastFocusedElement = null;

  function openModal(modal) {
    if (!modal) return;

    lastFocusedElement = document.activeElement;

    modal.classList.add("active");
    document.body.classList.add("modal-open");
    modal.setAttribute("aria-hidden", "false");

    modal.querySelector(".modal__close")?.focus();
  }

  function closeModal(modal) {
    if (!modal) return;

    modal.classList.remove("active");
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");

    lastFocusedElement?.focus();
  }

  function initModal(modal, triggers) {
    if (!modal) return;

    const closeBtn = modal.querySelector(".modal__close");
    const closeElements = modal.querySelectorAll("[data-close]");

    triggers.forEach(trigger => {
      trigger.addEventListener("click", function (e) {
        e.preventDefault();
        openModal(modal);
      });
    });

    closeBtn?.addEventListener("click", function () {
      closeModal(modal);
    });

    closeElements.forEach(element => {
      element.addEventListener("click", function () {
        closeModal(modal);
      });
    });
  }

  initModal(buyModal, buyTriggers);
  initModal(contactModal, contactTriggers);

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal.active").forEach(modal => {
        closeModal(modal);
      });
    }
  });
});

// animation
const animItems = document.querySelectorAll('._anim__items');

if (animItems.length > 0) {
    window.addEventListener('scroll', animOnScroll);
    function animOnScroll() {
        for (let i = 0; i < animItems.length; i++){
            const animItem = animItems[i];
            const animItemHeight = animItem.offsetHeight;
            const animItemOffset = offset(animItem).top;
            const animStart = 4;

            let animItemPoint = window.innerHeight - animItemHeight / animStart;

            if (animItemHeight > window.innerHeight) {
                animItemPoint = window.innerHeight - window.innerHeight / animStart;
            }

            if ((pageYOffset > animItemOffset - animItemPoint) && pageYOffset < (animItemOffset + animItemHeight)) {
                animItem.classList.add('_active');
            } else {
                if (!animItem.classList.contains('_anim__stop')) {
                    animItem.classList.remove('_active');
                }
                
            }
        }
    }
    function offset(el) {
        const rect = el.getBoundingClientRect(),
            scrollLeft = window.pageXOffset || document.documentElement.scrollLeft,
            scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        return {
            top: rect.top + scrollTop,
            left: rect.left + scrollLeft
        }
    }
    animOnScroll();
}

window.addEventListener('load', () => {
    const intro = document.querySelector('.hero__intro');
    const hero = document.querySelector('.hero');

    setTimeout(() => {
        intro.classList.add('_hide');
    }, 1200);

    setTimeout(() => {
        hero.classList.add('_loaded');
    }, 1500);
});


// cursor
document.addEventListener("DOMContentLoaded", function () {
  const cursor = document.querySelector(".cursor");

  if (!cursor) return;

  document.addEventListener("mousemove", function (e) {
    cursor.style.left = `${e.clientX}px`;
    cursor.style.top = `${e.clientY}px`;
  });

  const hoverElements = document.querySelectorAll("a, button, .gallery__item");

  hoverElements.forEach(function (el) {
    el.addEventListener("mouseenter", function () {
      cursor.classList.add("is-hover");
    });

    el.addEventListener("mouseleave", function () {
      cursor.classList.remove("is-hover");
    });
  });

  const fragment = document.querySelector(".almanac-fragment");

fragment?.addEventListener("mouseenter", function () {
  cursor.style.display = "none";
});

fragment?.addEventListener("mouseleave", function () {
  cursor.style.display = "block";
});
});

