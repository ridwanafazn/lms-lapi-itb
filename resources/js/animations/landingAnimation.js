import gsap from "gsap";
import ScrollTrigger from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);

/* =========================
   FOOTER PARALLAX
   ========================= */

function initFooterParallax() {

    const footerBg = document.querySelector("#footer-bg");
    const overlay  = document.querySelector("#footer-overlay");
    const trigger  = document.querySelector("#footer-trigger");

    if (!footerBg || !overlay || !trigger) return;

    // BACKGROUND PARALLAX
    gsap.fromTo(
        footerBg,
        { y: "-10%" },
        {
            y: "10%",
            ease: "none",
            scrollTrigger: {
                trigger: trigger,
                start: "top bottom",
                end: "bottom top",
                scrub: 1
            }
        }
    );

    // OVERLAY FADE OUT (REVEAL FOOTER)
    gsap.fromTo(
        overlay,
        { opacity: 1 },
        {
            opacity: 0,
            ease: "power2.out",
            scrollTrigger: {
                trigger: trigger,
                start: "top bottom",
                end: "top center",
                scrub: 1
            }
        }
    );

    gsap.fromTo(
        "#footer-content",
        { opacity: 0, y: 40, scale: 0.95 },
        {
            opacity: 1,
            y: 0,
            scale: 1,
            scrollTrigger: {
                trigger: trigger,
                start: "top 80%",
                end: "bottom bottom ",
                scrub: 1
            }
        }
    );

    

}

/* =========================
   NAVBAR SCROLL ANIMATION
   ========================= */

function initNavbarAnimation() {

    const nav = document.querySelector("#floating-navbar");
    if (!nav) return;

    gsap.fromTo(
        nav,
        { y: 0, opacity: 1 },
        {
            y: -10,
            opacity: 0.95,
            ease: "none",
            scrollTrigger: {
                start: 50,
                end: 200,
                scrub: true
            }
        }
    );
}

/* =========================
   MASTER INIT (EXPORT)
   ========================= */
export default function initLandingAnimations() {
    initFooterParallax();
    initNavbarAnimation();
    ScrollTrigger.refresh();
}