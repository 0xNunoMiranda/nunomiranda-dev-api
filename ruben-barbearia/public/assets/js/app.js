/**
 * Public Site JavaScript
 */

(function() {
  'use strict';

  // Mobile Menu Toggle
  const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
  const navLinks = document.querySelector('.nav-links');

  if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', () => {
      mobileMenuBtn.classList.toggle('active');
      navLinks.classList.toggle('open');
    });
  }

  // Smooth Scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      e.preventDefault();
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
        // Close mobile menu if open
        if (navLinks) navLinks.classList.remove('open');
        if (mobileMenuBtn) mobileMenuBtn.classList.remove('active');
      }
    });
  });

  // Header scroll effect
  const header = document.querySelector('.header');
  if (header) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 50) {
        header.classList.add('scrolled');
      } else {
        header.classList.remove('scrolled');
      }
    });
  }

  // Booking Form
  const bookingForm = document.getElementById('booking-form');
  const bookingDate = document.getElementById('booking-date');
  const bookingTime = document.getElementById('booking-time');
  const bookingSuccess = document.getElementById('booking-success');

  if (bookingDate && bookingTime) {
    // Load available slots when date changes
    bookingDate.addEventListener('change', async function() {
      const date = this.value;
      if (!date) return;

      bookingTime.disabled = true;
      bookingTime.innerHTML = '<option value="">A carregar...</option>';

      try {
        const response = await fetch(`/api/booking/slots?date=${date}`);
        const data = await response.json();
        
        bookingTime.innerHTML = '<option value="">Selecione uma hora</option>';
        
        if (data.slots && data.slots.length > 0) {
          data.slots.forEach(slot => {
            const option = document.createElement('option');
            option.value = slot;
            option.textContent = slot;
            bookingTime.appendChild(option);
          });
        } else {
          bookingTime.innerHTML = '<option value="">Sem disponibilidade nesta data</option>';
        }
        
        bookingTime.disabled = false;
      } catch (error) {
        console.error('Error loading slots:', error);
        bookingTime.innerHTML = '<option value="">Erro ao carregar horários</option>';
      }
    });
  }

  if (bookingForm) {
    bookingForm.addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const submitBtn = this.querySelector('button[type="submit"]');
      const originalText = submitBtn.textContent;
      submitBtn.textContent = 'A processar...';
      submitBtn.disabled = true;

      const formData = new FormData(this);
      const data = Object.fromEntries(formData.entries());

      try {
        const response = await fetch('/api/booking', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            customer_name: data.name,
            customer_phone: data.phone,
            service_id: parseInt(data.service_id),
            staff_id: data.staff_id ? parseInt(data.staff_id) : null,
            booking_date: data.date,
            booking_time: data.time,
            notes: data.notes || '',
          }),
        });

        const result = await response.json();

        if (response.ok) {
          bookingForm.style.display = 'none';
          bookingSuccess.style.display = 'block';
        } else {
          throw new Error(result.message || 'Erro ao criar marcação');
        }
      } catch (error) {
        alert('Erro: ' + error.message);
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
      }
    });
  }

  // Gallery lightbox
  const galleryItems = document.querySelectorAll('.gallery-item img');
  
  galleryItems.forEach(img => {
    img.addEventListener('click', function() {
      const lightbox = document.createElement('div');
      lightbox.className = 'lightbox';
      lightbox.innerHTML = `
        <button class="lightbox-close">&times;</button>
        <img src="${this.src}" alt="${this.alt}">
      `;
      document.body.appendChild(lightbox);
      document.body.style.overflow = 'hidden';

      lightbox.addEventListener('click', function(e) {
        if (e.target === lightbox || e.target.classList.contains('lightbox-close')) {
          lightbox.remove();
          document.body.style.overflow = '';
        }
      });
    });
  });

  // Animation on scroll
  const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('animate-in');
        observer.unobserve(entry.target);
      }
    });
  }, observerOptions);

  document.querySelectorAll('.service-card, .about-content, .contact-item').forEach(el => {
    el.classList.add('animate-target');
    observer.observe(el);
  });

})();
