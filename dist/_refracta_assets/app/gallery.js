// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
        e.preventDefault()
        const target = document.querySelector(this.getAttribute("href"))
        target.scrollIntoView({ behavior: "smooth" })
        // Reset active links
        document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
            anchor.classList.remove("active")
        })
        this.classList.add("active")
    })
})

// Update timeline on scroll
document.addEventListener('DOMContentLoaded', () => {
    let currentActiveYear = false;
    const offsetTrigger = 150;
    // All years elements
    const yearElements = document.querySelectorAll('.album-year');
    let isTicking = false;

    // New year found
    function onYearChange(newYear) {
        // Reset active links
        document.querySelectorAll('.timeline-year').forEach((anchor) => {
            anchor.classList.remove("active")
        })
        // Add active link
        document.getElementById('timeline-year-' + newYear).classList.add("active")
        // Scroll to timeline year
        document.getElementById('timeline-year-' + newYear).scrollIntoView({ behavior: "smooth" })
    }

    function checkScrollPosition() {
        let newYearCandidate = currentActiveYear;

        yearElements.forEach(el => {
            const rect = el.getBoundingClientRect();
            if (rect.top <= offsetTrigger) {
                newYearCandidate = el.dataset.year;
            }
        });

        if (newYearCandidate !== currentActiveYear) {
            currentActiveYear = newYearCandidate;
            onYearChange(currentActiveYear);
        }

        isTicking = false;
    }

    window.addEventListener('scroll', () => {
        if (!isTicking) {
            window.requestAnimationFrame(() => {
                checkScrollPosition();
            });
            isTicking = true;
        }
    });

    checkScrollPosition();
});
