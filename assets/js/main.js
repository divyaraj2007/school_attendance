// assets/js/main.js - Interactive helpers

document.addEventListener('DOMContentLoaded', function () {
  // 1. Sidebar Toggle
  const sidebar = document.getElementById('appSidebar');
  const toggleBtn = document.getElementById('sidebarToggleBtn');
  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('show');
    });
  }

  // Close sidebar when clicking outside on mobile
  document.addEventListener('click', function (e) {
    if (window.innerWidth < 992 && sidebar && sidebar.classList.contains('show')) {
      if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
        sidebar.classList.remove('show');
      }
    }
  });

  // 2. Show/Hide Password Toggle
  const togglePassBtns = document.querySelectorAll('.toggle-password-btn');
  togglePassBtns.forEach(btn => {
    btn.addEventListener('click', function () {
      const targetId = this.getAttribute('data-target');
      const input = document.getElementById(targetId);
      if (input) {
        const isPass = input.getAttribute('type') === 'password';
        input.setAttribute('type', isPass ? 'text' : 'password');
        const icon = this.querySelector('i');
        if (icon) {
          icon.classList.toggle('fa-eye', !isPass);
          icon.classList.toggle('fa-eye-slash', isPass);
        }
      }
    });
  });

  // 3. Mark All Present / Absent Helpers
  const markAllPresentBtn = document.getElementById('markAllPresentBtn');
  if (markAllPresentBtn) {
    markAllPresentBtn.addEventListener('click', function () {
      document.querySelectorAll('.attendance-toggle-group input[value="present"]').forEach(r => {
        r.checked = true;
      });
    });
  }

  const markAllAbsentBtn = document.getElementById('markAllAbsentBtn');
  if (markAllAbsentBtn) {
    markAllAbsentBtn.addEventListener('click', function () {
      document.querySelectorAll('.attendance-toggle-group input[value="absent"]').forEach(r => {
        r.checked = true;
      });
    });
  }

  // 4. Dynamic Sections & Subjects by Class
  const classSelect = document.getElementById('classSelect');
  const sectionSelect = document.getElementById('sectionSelect');
  const subjectSelect = document.getElementById('subjectSelect');

  if (classSelect) {
    classSelect.addEventListener('change', function () {
      const classId = this.value;
      if (!classId) return;

      // Fetch Sections
      if (sectionSelect) {
        fetch(`${window.BASE_URL || ''}/api/get_sections.php?class_id=${classId}`)
          .then(res => res.json())
          .then(data => {
            sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
            if (data && data.length > 0) {
              data.forEach(sec => {
                const opt = document.createElement('option');
                opt.value = sec.id;
                opt.textContent = `Section ${sec.name}`;
                sectionSelect.appendChild(opt);
              });
            }
          })
          .catch(err => console.error("Error fetching sections:", err));
      }

      // Fetch Subjects
      if (subjectSelect) {
        fetch(`${window.BASE_URL || ''}/api/get_subjects.php?class_id=${classId}`)
          .then(res => res.json())
          .then(data => {
            subjectSelect.innerHTML = '<option value="">-- Select Subject --</option>';
            if (data && data.length > 0) {
              data.forEach(sub => {
                const opt = document.createElement('option');
                opt.value = sub.id;
                opt.textContent = `${sub.name} (${sub.code})`;
                subjectSelect.appendChild(opt);
              });
            }
          })
          .catch(err => console.error("Error fetching subjects:", err));
      }
    });
  }

  // 5. Confirmation helper on delete buttons
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
      const msg = this.getAttribute('data-confirm') || 'Are you sure you want to perform this action?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // 6. Auto-dismiss alerts after 5 seconds
  setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
      const bsAlert = bootstrap.Alert.getInstance(alert);
      if (bsAlert) {
        bsAlert.close();
      }
    });
  }, 5000);

  // 7. Register PWA Service Worker for Mobile App Support
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      const swUrl = `${window.BASE_URL || ''}/sw.js`;
      navigator.serviceWorker.register(swUrl)
        .then((reg) => {
          console.log('Smart Attendance Service Worker registered with scope:', reg.scope);
        })
        .catch((err) => {
          console.warn('Service Worker registration skipped or failed:', err);
        });
    });
  }

  // 8. PWA Install Prompt handling
  let deferredPrompt;
  const pwaInstallBtn = document.getElementById('pwaInstallBtn');
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    if (pwaInstallBtn) {
      pwaInstallBtn.style.display = 'inline-flex';
    }
  });

  if (pwaInstallBtn) {
    pwaInstallBtn.addEventListener('click', async () => {
      if (deferredPrompt) {
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`User response to PWA install prompt: ${outcome}`);
        deferredPrompt = null;
        pwaInstallBtn.style.display = 'none';
      }
    });
  }
});
