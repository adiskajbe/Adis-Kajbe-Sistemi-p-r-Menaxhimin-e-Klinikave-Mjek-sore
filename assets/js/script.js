<<<<<<< HEAD
document.addEventListener('DOMContentLoaded', function() {
    // Konfirmimi për fshirje (mos e fshi këtë, është funksionaliteti yt)
    document.querySelectorAll('[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const message = form.getAttribute('data-confirm') || 'A dëshironi të vazhdoni?';
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Shtimi i Loading State për të gjitha format (Pacient, Mjek, Termin)
    const forms = document.querySelectorAll('form.patient-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) return; 

            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner"></span> Duke procesuar...';
                
                // Rikthimi nëse serveri vonohet shumë (maksimumi 5 sekonda)
                setTimeout(() => {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                }, 5000);
            }
        });
    });
});
=======
document.querySelectorAll('[data-confirm]').forEach((form) => {
  form.addEventListener('submit', (event) => {
    const message = form.getAttribute('data-confirm') || 'A dëshironi të vazhdoni?';
    if (!window.confirm(message)) event.preventDefault();
  });
});
>>>>>>> 8ea089d10acf3afd02a4571590b990271931a742
