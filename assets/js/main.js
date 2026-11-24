/**
 * assets/js/main.js - Blue Lagoon Villa
 *
 * Client-side features:
 *  1. Form validation (register, login, availability search, booking, room admin)
 *  2. Date logic: no past dates, check-out always after check-in, maximum stay
 *  3. Live nights and price calculation on the booking form (DOM updates)
 *  4. AJAX availability check (fetch API) on the booking form
 *  5. Guest-capacity handling per room, character counter, image preview
 *  6. Confirm dialogs for forms with a data-confirm attribute
 *
 * Client-side checks are a convenience only: the PHP code validates
 * everything again and recalculates all prices on the server.
 */
(function () {
    'use strict';

    var MAX_NIGHTS = 30;
    var MS_PER_DAY = 24 * 60 * 60 * 1000;

    /* =========================================================
     * Small helpers
     * ======================================================= */

    function $(selector, root) {
        return (root || document).querySelector(selector);
    }

    /** Local date as YYYY-MM-DD (not UTC, so Sri Lanka time is respected). */
    function toISODate(date) {
        var m = String(date.getMonth() + 1).padStart(2, '0');
        var d = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + m + '-' + d;
    }

    function todayISO() {
        return toISODate(new Date());
    }

    /** Parses "YYYY-MM-DD" into a UTC timestamp; returns NaN when invalid (e.g. 2026-02-31). */
    function parseISO(value) {
        var match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
        if (!match) { return NaN; }
        var y = Number(match[1]), m = Number(match[2]), d = Number(match[3]);
        var t = Date.UTC(y, m - 1, d);
        var check = new Date(t);
        if (check.getUTCFullYear() !== y || check.getUTCMonth() !== m - 1 || check.getUTCDate() !== d) {
            return NaN;
        }
        return t;
    }

    function addDaysISO(value, days) {
        var t = parseISO(value);
        if (isNaN(t)) { return ''; }
        var d = new Date(t + days * MS_PER_DAY);
        var m = String(d.getUTCMonth() + 1).padStart(2, '0');
        var day = String(d.getUTCDate()).padStart(2, '0');
        return d.getUTCFullYear() + '-' + m + '-' + day;
    }

    function nightsBetween(checkIn, checkOut) {
        var a = parseISO(checkIn), b = parseISO(checkOut);
        if (isNaN(a) || isNaN(b) || b <= a) { return 0; }
        return Math.round((b - a) / MS_PER_DAY);
    }

    function formatLKR(amount) {
        return 'LKR ' + Number(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    /* ---------- field error display (Bootstrap is-invalid) ---------- */

    function feedbackFor(field) {
        var holder = field.parentElement;
        return holder ? holder.querySelector('.invalid-feedback') : null;
    }

    function setError(field, message) {
        field.classList.add('is-invalid');
        field.classList.remove('is-valid-field');
        field.setAttribute('aria-invalid', 'true');
        var box = feedbackFor(field);
        if (box) { box.textContent = message; }
    }

    function clearError(field) {
        field.classList.remove('is-invalid');
        field.removeAttribute('aria-invalid');
        var box = feedbackFor(field);
        if (box) { box.textContent = ''; }
    }

    /** Runs a list of [field, validatorFn] pairs; returns true when all pass. */
    function runChecks(checks) {
        var firstBad = null;
        checks.forEach(function (pair) {
            var field = pair[0];
            if (!field) { return; }
            var message = pair[1]();
            if (message) {
                setError(field, message);
                if (!firstBad) { firstBad = field; }
            } else {
                clearError(field);
            }
        });
        if (firstBad) { firstBad.focus(); }
        return firstBad === null;
    }

    /** Clears a field's error as soon as the user edits it. */
    function clearOnInput(form) {
        form.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('is-invalid')) {
                clearError(e.target);
            }
        });
        form.addEventListener('change', function (e) {
            if (e.target.classList && e.target.classList.contains('is-invalid')) {
                clearError(e.target);
            }
        });
    }

    /* ---------- reusable validators ---------- */

    function validateStayFields(checkIn, checkOut, guests, maxGuests) {
        var today = todayISO();
        return [
            [checkIn, function () {
                if (!checkIn.value) { return 'Please select a check-in date.'; }
                if (isNaN(parseISO(checkIn.value))) { return 'Please enter a valid check-in date.'; }
                if (checkIn.value < today) { return 'Check-in date cannot be in the past.'; }
                return '';
            }],
            [checkOut, function () {
                if (!checkOut.value) { return 'Please select a check-out date.'; }
                if (isNaN(parseISO(checkOut.value))) { return 'Please enter a valid check-out date.'; }
                if (checkIn.value && !isNaN(parseISO(checkIn.value))) {
                    if (checkOut.value <= checkIn.value) { return 'Check-out must be after check-in.'; }
                    if (nightsBetween(checkIn.value, checkOut.value) > MAX_NIGHTS) {
                        return 'A single booking cannot exceed ' + MAX_NIGHTS + ' nights.';
                    }
                }
                return '';
            }],
            [guests, function () {
                var n = parseInt(guests.value, 10);
                if (isNaN(n) || n < 1) { return 'Please select the number of guests.'; }
                if (maxGuests && n > maxGuests) {
                    return 'This room accommodates a maximum of ' + maxGuests + ' guest(s).';
                }
                return '';
            }]
        ];
    }

    /**
     * Keeps the check-out picker consistent with check-in:
     * min check-out = check-in + 1 day, and an invalid check-out is cleared.
     */
    function linkDateFields(checkIn, checkOut) {
        if (!checkIn || !checkOut) { return; }

        function sync() {
            var minOut = checkIn.value && !isNaN(parseISO(checkIn.value))
                ? addDaysISO(checkIn.value, 1)
                : addDaysISO(todayISO(), 1);
            checkOut.min = minOut;
            if (checkOut.value && checkOut.value < minOut) {
                checkOut.value = '';
            }
        }
        checkIn.min = todayISO();
        checkIn.addEventListener('change', sync);
        sync();
    }

    /* =========================================================
     * 1. Registration form
     * ======================================================= */
    function initRegisterForm(form) {
        var f = form.elements;
        clearOnInput(form);

        form.addEventListener('submit', function (e) {
            var ok = runChecks([
                [f.name, function () {
                    var v = f.name.value.trim();
                    if (v.length < 2) { return 'Please enter your full name (at least 2 characters).'; }
                    if (v.length > 100) { return 'Name must not exceed 100 characters.'; }
                    if (!/^[\p{L}\p{M} .'\-]+$/u.test(v)) {
                        return 'Name can only contain letters, spaces, dots, apostrophes and hyphens.';
                    }
                    return '';
                }],
                [f.email, function () {
                    var v = f.email.value.trim();
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v)) { return 'Please enter a valid email address.'; }
                    return '';
                }],
                [f.contact_number, function () {
                    var v = f.contact_number.value.replace(/[\s-]/g, '');
                    if (!/^\+?[0-9]{9,15}$/.test(v)) {
                        return 'Enter a valid contact number (9-15 digits, optional leading +).';
                    }
                    return '';
                }],
                [f.password, function () {
                    var v = f.password.value;
                    if (v.length < 8 || !/[A-Za-z]/.test(v) || !/[0-9]/.test(v)) {
                        return 'Password must be at least 8 characters and include a letter and a number.';
                    }
                    return '';
                }],
                [f.confirm_password, function () {
                    if (f.confirm_password.value !== f.password.value) { return 'Passwords do not match.'; }
                    return '';
                }]
            ]);
            if (!ok) { e.preventDefault(); }
        });
    }

    /* =========================================================
     * 2. Login form
     * ======================================================= */
    function initLoginForm(form) {
        var f = form.elements;
        clearOnInput(form);

        form.addEventListener('submit', function (e) {
            var ok = runChecks([
                [f.email, function () {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(f.email.value.trim())
                        ? '' : 'Please enter a valid email address.';
                }],
                [f.password, function () {
                    return f.password.value === '' ? 'Please enter your password.' : '';
                }]
            ]);
            if (!ok) { e.preventDefault(); }
        });
    }

    /* =========================================================
     * 3. Availability search (home, availability page, room page)
     * ======================================================= */
    function initSearchForm(form) {
        var f = form.elements;
        linkDateFields(f.check_in, f.check_out);
        clearOnInput(form);

        form.addEventListener('submit', function (e) {
            var ok = runChecks(validateStayFields(f.check_in, f.check_out, f.guests, 0));
            if (!ok) { e.preventDefault(); }
        });
    }

    /* =========================================================
     * 4. Booking form: live price, capacity, AJAX availability
     * ======================================================= */
    function initBookingForm(form) {
        var f = form.elements;
        var room      = f.room_id;
        var checkIn   = f.check_in;
        var checkOut  = f.check_out;
        var guests    = f.guests;
        var requests  = f.special_requests;
        var counter   = $('#requestCount');
        var message   = $('#availabilityMsg');
        var sRoom     = $('#summaryRoom');
        var sPrice    = $('#summaryPrice');
        var sNights   = $('#summaryNights');
        var sTotal    = $('#summaryTotal');
        var endpoint  = form.getAttribute('data-availability-url');

        var timer = null;
        var controller = null;
        var lastAvailable = null;   // null = unknown, true/false = result of the last check

        function selectedOption() {
            return room.options[room.selectedIndex];
        }
        function selectedPrice() {
            var opt = selectedOption();
            return opt && opt.value ? parseFloat(opt.getAttribute('data-price')) : NaN;
        }
        function selectedMax() {
            var opt = selectedOption();
            return opt && opt.value ? parseInt(opt.getAttribute('data-max'), 10) : 0;
        }

        /** Disable guest options that exceed the chosen room's capacity. */
        function applyCapacity() {
            var max = selectedMax();
            Array.prototype.forEach.call(guests.options, function (opt) {
                opt.disabled = max > 0 && parseInt(opt.value, 10) > max;
            });
            if (max > 0 && parseInt(guests.value, 10) > max) {
                guests.value = String(max);
            }
        }

        /** Updates the summary box using textContent (no HTML is ever injected). */
        function updateSummary() {
            var opt = selectedOption();
            var price = selectedPrice();
            var nights = nightsBetween(checkIn.value, checkOut.value);

            sRoom.textContent  = opt && opt.value ? opt.getAttribute('data-name') : '\u2013';
            sPrice.textContent = isNaN(price) ? '\u2013' : formatLKR(price);
            sNights.textContent = nights > 0 ? String(nights) : '\u2013';
            sTotal.textContent = (!isNaN(price) && nights > 0) ? formatLKR(price * nights) : '\u2013';
        }

        function showMessage(type, text) {
            message.textContent = '';
            if (!text) { return; }
            var box = document.createElement('div');
            box.className = 'alert alert-' + type;
            box.setAttribute('role', 'alert');
            box.textContent = text;
            message.appendChild(box);
        }

        /** Asks the server whether the room is free (only when the inputs look valid). */
        function checkAvailability() {
            lastAvailable = null;
            if (controller) { controller.abort(); }

            var nights = nightsBetween(checkIn.value, checkOut.value);
            if (!endpoint || !room.value || nights < 1 || nights > MAX_NIGHTS ||
                checkIn.value < todayISO() || window.fetch === undefined) {
                showMessage('', '');
                return;
            }

            var query = new URLSearchParams({
                room_id: room.value,
                check_in: checkIn.value,
                check_out: checkOut.value,
                guests: guests.value
            });

            showMessage('info', 'Checking availability...');
            controller = typeof AbortController !== 'undefined' ? new AbortController() : null;

            fetch(endpoint + '?' + query.toString(), {
                headers: { 'Accept': 'application/json' },
                signal: controller ? controller.signal : undefined
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.ok) {
                        lastAvailable = data.available;
                        showMessage(data.available ? 'success' : 'danger',
                            data.message + (data.available ? ' Estimated total: ' + data.total_formatted + '.' : ''));
                    } else {
                        showMessage('warning', (data.errors || ['Could not check availability.']).join(' '));
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') { return; }
                    showMessage('', '');   // stay silent: the server re-checks on submit anyway
                });
        }

        function scheduleCheck() {
            window.clearTimeout(timer);
            timer = window.setTimeout(checkAvailability, 400);
        }

        function onStayChanged() {
            applyCapacity();
            updateSummary();
            scheduleCheck();
        }

        linkDateFields(checkIn, checkOut);
        clearOnInput(form);

        room.addEventListener('change', onStayChanged);
        checkIn.addEventListener('change', onStayChanged);
        checkOut.addEventListener('change', onStayChanged);
        guests.addEventListener('change', onStayChanged);

        if (requests && counter) {
            requests.addEventListener('input', function () {
                counter.textContent = String(requests.value.length);
            });
        }

        form.addEventListener('submit', function (e) {
            var checks = [[room, function () {
                return room.value ? '' : 'Please select a room.';
            }]].concat(validateStayFields(checkIn, checkOut, guests, selectedMax()));

            var ok = runChecks(checks);

            if (ok && lastAvailable === false) {
                ok = false;
                showMessage('danger', 'This room is not available for the selected dates. Please choose different dates or another room.');
                checkIn.focus();
            }
            if (!ok) { e.preventDefault(); }
        });

        // Initial state (values may be pre-filled from the room page or a failed submit)
        applyCapacity();
        updateSummary();
        if (room.value && checkIn.value && checkOut.value) { scheduleCheck(); }
    }

    /* =========================================================
     * 5. Admin room form + image preview
     * ======================================================= */
    function initRoomForm(form) {
        var f = form.elements;
        clearOnInput(form);

        var fileInput = f.image;
        var preview = $('#imagePreview');
        var originalSrc = preview ? preview.getAttribute('src') : '';
        var objectUrl = null;

        if (fileInput && preview) {
            fileInput.addEventListener('change', function () {
                if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
                var file = fileInput.files && fileInput.files[0];
                if (file && /^image\/(jpeg|png|webp)$/.test(file.type)) {
                    objectUrl = URL.createObjectURL(file);
                    preview.src = objectUrl;
                } else {
                    preview.src = originalSrc;
                }
            });
        }

        form.addEventListener('submit', function (e) {
            var ok = runChecks([
                [f.room_name, function () {
                    var l = f.room_name.value.trim().length;
                    return (l < 2 || l > 100) ? 'Room name must be between 2 and 100 characters.' : '';
                }],
                [f.room_type, function () {
                    var l = f.room_type.value.trim().length;
                    return (l < 2 || l > 50) ? 'Room type must be between 2 and 50 characters.' : '';
                }],
                [f.description, function () {
                    var l = f.description.value.trim().length;
                    return (l < 10 || l > 2000) ? 'Description must be between 10 and 2000 characters.' : '';
                }],
                [f.facilities, function () {
                    return f.facilities.value.replace(/[,\s]/g, '') === ''
                        ? 'Enter at least one facility (separate several with commas).' : '';
                }],
                [f.price_per_night, function () {
                    var v = f.price_per_night.value.trim();
                    return (!/^\d{1,8}(\.\d{1,2})?$/.test(v) || parseFloat(v) <= 0)
                        ? 'Enter a price greater than 0 using digits only, e.g. 18500 or 18500.50.' : '';
                }],
                [f.max_guests, function () {
                    var n = parseInt(f.max_guests.value, 10);
                    return (isNaN(n) || n < 1 || n > 20) ? 'Maximum guests must be a whole number between 1 and 20.' : '';
                }],
                [fileInput, function () {
                    var file = fileInput.files && fileInput.files[0];
                    if (!file) { return ''; }
                    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { return 'Only JPG, PNG or WEBP images are allowed.'; }
                    if (file.size > 2 * 1024 * 1024) { return 'The image is too large. Maximum size is 2 MB.'; }
                    return '';
                }]
            ]);
            if (!ok) { e.preventDefault(); }
        });
    }

    /* =========================================================
     * 6. Confirmation dialogs (data-confirm on any form)
     * ======================================================= */
    function initConfirmForms() {
        document.addEventListener('submit', function (e) {
            var form = e.target;
            if (!form.getAttribute) { return; }
            var text = form.getAttribute('data-confirm');
            if (text && !window.confirm(text)) {
                e.preventDefault();
            }
        });
    }

    /* =========================================================
     * Start-up
     * ======================================================= */
    document.addEventListener('DOMContentLoaded', function () {
        initConfirmForms();

        Array.prototype.forEach.call(document.querySelectorAll('form[data-validate]'), function (form) {
            switch (form.getAttribute('data-validate')) {
                case 'register': initRegisterForm(form); break;
                case 'login':    initLoginForm(form);    break;
                case 'search':   initSearchForm(form);   break;
                case 'booking':  initBookingForm(form);  break;
                case 'room':     initRoomForm(form);     break;
            }
        });

        // Flash messages fade away after a few seconds (success and info only)
        Array.prototype.forEach.call(document.querySelectorAll('#flashArea .alert-success, #flashArea .alert-info'), function (alertBox) {
            window.setTimeout(function () {
                if (window.bootstrap && window.bootstrap.Alert) {
                    window.bootstrap.Alert.getOrCreateInstance(alertBox).close();
                }
            }, 8000);
        });
    });
})();
