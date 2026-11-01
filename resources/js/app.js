import './bootstrap';
import initLandingAnimations from "./animations/landingAnimation";
import { ScrollTrigger } from "gsap/ScrollTrigger";

function cleanupAnimations() {
    ScrollTrigger.getAll().forEach(t => t.kill());
}

function handleNavigation() {
    cleanupAnimations();
    const isLandingPage = document.querySelector("#floating-navbar") || document.querySelector("#footer-bg");
    if (!isLandingPage) {
        return;
    }

    setTimeout(() => {
        initLandingAnimations();
    }, 50);
}

// Event Listeners
document.addEventListener("livewire:navigated", handleNavigation);
document.addEventListener("DOMContentLoaded", handleNavigation);
