document.addEventListener("DOMContentLoaded", () => {
  const animatedItems = document.querySelectorAll("[data-animate]");

if (animatedItems.length) {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    },
    {
      threshold: 0.15
    }
  );

  animatedItems.forEach((item) => observer.observe(item));
}


  const burger = document.querySelector(".burger");
  const nav = document.querySelector(".nav");
  const dropdownItems = document.querySelectorAll(".nav__item--dropdown");

  if (burger && nav) {
    burger.addEventListener("click", () => {
      burger.classList.toggle("active");
      nav.classList.toggle("active");
      document.body.classList.toggle("lock");
    });
  }

  dropdownItems.forEach((item) => {
    const button = item.querySelector(".nav__dropdown-btn");

    if (!button) return;

    button.addEventListener("click", (event) => {
      event.stopPropagation();

      dropdownItems.forEach((dropdown) => {
        if (dropdown !== item) {
          dropdown.classList.remove("active");
        }
      });

      item.classList.toggle("active");
    });
  });

  document.addEventListener("click", () => {
    dropdownItems.forEach((item) => {
      item.classList.remove("active");
    });
  });

  nav?.addEventListener("click", (event) => {
    event.stopPropagation();
  });

  document.querySelectorAll(".nav a").forEach((link) => {
  link.addEventListener("click", () => {
    nav?.classList.remove("active");
    burger?.classList.remove("active");
    document.body.classList.remove("lock");
  });
});

  window.addEventListener("resize", () => {
    if (window.innerWidth > 768) {
      nav?.classList.remove("active");
      burger?.classList.remove("active");
      document.body.classList.remove("lock");
    }
  });




  const scrollTopBtn = document.querySelector(".scroll-top");

if (scrollTopBtn) {
  window.addEventListener("scroll", () => {
    if (window.scrollY > 300) {
      scrollTopBtn.classList.add("show");
    } else {
      scrollTopBtn.classList.remove("show");
    }
  });

  scrollTopBtn.addEventListener("click", () => {
    window.scrollTo({
      top: 0,
      behavior: "smooth"
    });
  });
}
});


