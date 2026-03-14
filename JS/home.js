// Typing Effect
const textToType = "Move Better. Travel Smarter.";
const typingTarget = document.getElementById("typing-text");
const section = document.getElementById("explore-section");

let index = 0;
let hasStarted = false;

function typeEffect() {
    if (index < textToType.length) {
        typingTarget.textContent += textToType.charAt(index);
        index++;
        setTimeout(typeEffect, 100);
    }
}

// Intersection Observer for typing effect
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting && !hasStarted) {
            hasStarted = true;
            typeEffect();
        }
    });
}, { threshold: 0.3 });

if (section) {
    observer.observe(section);
}

// jQuery for fade effects on scroll
$(document).ready(function() {
    // Add fade-in class to elements
    $('.fade-in').each(function() {
        $(this).css('opacity', '0');
    });
    
    // Check if element is in viewport
    function isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top <= (window.innerHeight || document.documentElement.clientHeight) * 0.85 &&
            rect.bottom >= 0
        );
    }
    
    // Fade in elements on scroll
    function checkFadeElements() {
        $('.fade-in').each(function() {
            if (isElementInViewport(this)) {
                $(this).addClass('active');
            }
        });
    }
    
    // Initial check
    checkFadeElements();
    
    // Check on scroll
    $(window).on('scroll', function() {
        checkFadeElements();
    });
    
    // Mobile menu toggle
    $('[command="--toggle"]').on('click', function() {
        const target = $(this).attr('commandfor');
        $('#' + target).slideToggle(300);
    });
    
    // Smooth scrolling for anchor links
    $('a[href^="#"]').on('click', function(e) {
        if (this.hash !== "") {
            e.preventDefault();
            const hash = this.hash;
            $('html, body').animate({
                scrollTop: $(hash).offset().top - 80
            }, 800);
        }
    });
    
    // Responsive adjustments on window resize
    $(window).on('resize', function() {
        // Adjust service cards on mobile
        if ($(window).width() < 768) {
            $('.service-card').css('margin-bottom', '20px');
        } else {
            $('.service-card').css('margin-bottom', '0');
        }
    }).trigger('resize');
    
    // Add fade-in class to specific sections
    $('#service1, #service2, #service3').addClass('fade-in');
    $('.feature-section > div').addClass('fade-in');
    $('.about-section').addClass('fade-in');
    $('.max-w-6xl.mx-auto.px-6.py-20').addClass('fade-in');
    $('footer').addClass('fade-in');
    
    // Add loading animation for images
    $('img').on('load', function() {
        $(this).addClass('loaded');
    }).each(function() {
        if (this.complete) $(this).load();
    });
    
    // Handle video play on mobile
    $('.hero-video').each(function() {
        this.setAttribute('playsinline', '');
        this.setAttribute('muted', '');
        this.setAttribute('autoplay', '');
    });
    
    // Touch-friendly hover effects for mobile
    if ('ontouchstart' in window) {
        $('.hover\\:bg-\\[\\#e65500\\]').on('touchstart', function() {
            $(this).addClass('bg-[#e65500]');
        }).on('touchend', function() {
            $(this).removeClass('bg-[#e65500]');
        });
    }
});

// Additional responsive functions
function adjustLayoutForMobile() {
    const isMobile = window.innerWidth <= 768;
    
    // Adjust hero section for mobile
    const heroSection = document.querySelector('.relative.bg-white');
    if (heroSection && isMobile) {
        heroSection.style.padding = '40px 20px';
    }
    
    // Adjust service cards layout
    const serviceCards = document.querySelectorAll('#service1, #service2, #service3');
    if (serviceCards.length > 0 && isMobile) {
        serviceCards.forEach(card => {
            card.style.marginBottom = '20px';
        });
    }
    
    // Adjust feature sections
    const featureSections = document.querySelectorAll('.flex-col.md\\:flex-row');
    featureSections.forEach(section => {
        if (isMobile) {
            section.style.flexDirection = 'column';
            section.style.textAlign = 'center';
        }
    });
}

// Run on load and resize
window.addEventListener('load', adjustLayoutForMobile);
window.addEventListener('resize', adjustLayoutForMobile);