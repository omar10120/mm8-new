const itiInstances = new Map();

const mobileExamples = {
    bh: "+97336001234",      // Bahrain
    kw: "+96551234567",      // Kuwait
    om: "+96891234567",      // Oman
    qa: "+97450123456",      // Qatar
    sa: "+966501234567",     // Saudi Arabia
    ae: "+971501234567",     // United Arab Emirates
};
function initializeIntlTelInput() {
    const inputs = document.querySelectorAll(
        'input[type="tel"]:not([data-intl-initialized])'
    );

    const defaultCountryConfigEl = document.querySelector(".system-default-country-code");

    // dataset converts all `data-xxx` into camelCase automatically:
    const configDataset = defaultCountryConfigEl?.dataset || {};

    function toBool(value) {
        return value === "1" || value === "true";
    }

    // Parse `onlyCountriesArray` safely from string (e.g. "['us','gb']")
    function parseCountries(value) {
        if (!value || value === "[]" || value === "") return [];
        try {
            // Try to parse JSON-style or comma-separated strings
            if (value.startsWith("[")) {
                return JSON.parse(value.replace(/'/g, '"'));
            }
            return value.split(",").map(c => c.trim().toLowerCase());
        } catch {
            return [];
        }
    }

    const separateDialCode = configDataset.separateDialCode !== undefined
        ? toBool(configDataset.separateDialCode)
        : true; // default true

    const showSelectedDialCode = configDataset.showSelectedDialCode !== undefined
        ? toBool(configDataset.showSelectedDialCode)
        : false; // default false

    const autoInsertDialCode = configDataset.autoInsertDialCode !== undefined
        ? toBool(configDataset.autoInsertDialCode)
        : false; // default false

    const nationalMode = configDataset.nationalMode !== undefined
        ? toBool(configDataset.nationalMode)
        : false; // default false

    const onlyCountries = parseCountries(configDataset.onlyCountriesArray);
    const onlyCountriesMode = toBool(configDataset.onlyCountriesMode);

    // choose detected_country first, fallback to initial_country, then "us"
    const defaultCountry =
        (configDataset.detectedCountry || configDataset.initialCountry || "us").toLowerCase();

    inputs.forEach(input => {
        const iti = window.intlTelInput(input, {
            initialCountry: defaultCountry,
            autoInsertDialCode: autoInsertDialCode,
            nationalMode: nationalMode,
            formatOnDisplay: false,
            separateDialCode: separateDialCode,
            showSelectedDialCode: showSelectedDialCode,
            autoPlaceholder: configDataset.autoPlaceholder || "off",
            // Add onlyCountries only if mode is enabled and array is not empty
            ...(onlyCountriesMode && onlyCountries.length > 0
            ? { onlyCountries: onlyCountries }
            : {}),
            utilsScript:
                "https://cdn.jsdelivr.net/npm/intl-tel-input@19.2.15/build/js/utils.js"
        });

        // Override _setFlag after init
        const originalSetFlag = iti._setFlag;
        iti._setFlag = function(countryCode) {
            const result = originalSetFlag.call(this, countryCode);

            if (this.options.showSelectedDialCode && this.isRTL) {
                const selectedFlagWidth =
                    this.selectedFlag.offsetWidth ||
                    this._getHiddenSelectedFlagWidth();
                this.telInput.style.paddingLeft = `${selectedFlagWidth + 6}px`;
                this.telInput.style.paddingRight = "";
            }

            return result;
        };

        iti._setFlag(iti.getSelectedCountryData().iso2);

        // itiInstances.set(input, iti);
        // input.setAttribute("data-intl-initialized", "true");

        // ✅ Move name & value to a hidden input
        const originalName = input.getAttribute('name');
        const originalValue = input.value;
        input.removeAttribute('name'); // remove name from visible input

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = originalName;
        input.parentNode.appendChild(hiddenInput);

        try {
            const originalSetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
            const originalGetter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').get;

            Object.defineProperty(hiddenInput, 'value', {
                set(newValue) {
                    originalSetter.call(this, newValue);
                    iti.setNumber(newValue); // uses intlTelInput to format & update country
                },
                get() {
                    return originalGetter.call(this);
                }
            });
        } catch (e) {
        }

        const hiddenCountryCodeInput = document.createElement('input');
        hiddenCountryCodeInput.type = 'hidden';
        hiddenCountryCodeInput.name = originalName + '_country_code';
        input.parentNode.appendChild(hiddenCountryCodeInput);

        // ✅ Set initial value if exists
        if (originalValue) {
            iti.setNumber(originalValue);
            hiddenInput.value = iti.getNumber(); // formatted full number
            hiddenCountryCodeInput.value = iti?.getSelectedCountryData()?.dialCode;

            // Set data-value and data-country
            input.setAttribute('data-value', iti.getNumber());
            input.setAttribute('data-country', iti.getSelectedCountryData()?.iso2);
        }

        // ✅ Keep updating hidden input on user change
        input.addEventListener('input', () => {
            hiddenInput.value = iti.getNumber();
            hiddenCountryCodeInput.value = iti?.getSelectedCountryData()?.dialCode;
        });

        input.addEventListener('countrychange', () => {
            hiddenInput.value = iti.getNumber();
            hiddenCountryCodeInput.value = iti?.getSelectedCountryData()?.dialCode;
        });

        iti._setFlag(iti.getSelectedCountryData().iso2);
        itiInstances.set(input, iti);
        input.setAttribute("data-intl-initialized", "true");
    });
}

function keepOnlyNumbers(str) {
    return str.replace(/\D/g, "");
}

function getLocalExampleNumberLength(iti) {
    try {
        const iso2 = iti.getSelectedCountryData().iso2;
        let dialCode = iti.getSelectedCountryData().dialCode;

        if (!iso2 || !dialCode) {
            console.warn("No country or dial code found");
            return 12; // fallback
        }

        dialCode = dialCode.toString();

        // Use example from your predefined list or intl-tel-input utils fallback
        let example = mobileExamples[iso2];
        if (!example && window.intlTelInputUtils) {
            example = intlTelInputUtils.getExampleNumber(
                iso2,
                true,
                intlTelInputUtils.numberFormat.E164
            );
        }

        if (!example) {
            console.warn("No example number found for", iso2);
            return 12; // fallback
        }

        const digitsOnly = example.replace(/\D/g, "");

        // Check if dialCode is at start of digitsOnly
        const dialCodeIndex = digitsOnly.startsWith(dialCode) ? 0 : -1;

        const nationalDigits =
            dialCodeIndex === 0
                ? digitsOnly.slice(dialCode.length)
                : digitsOnly; // fallback to full if dialCode not found at start

        return nationalDigits.length;
    } catch (e) {
        console.warn("Fallback to default local max length (12)", e);
        return 12;
    }
}

function validatePhoneInput(input) {
    const iti = itiInstances.get(input);
    if (!iti || typeof iti.isValidNumber !== "function") return false;

    const fullNumber = iti.getNumber(); // E164 format
    const dialCode = iti.getSelectedCountryData().dialCode;
    const digitsOnly = keepOnlyNumbers(fullNumber);
    const dialCodeIndex = digitsOnly.indexOf(dialCode);
    const localNumber =
        dialCodeIndex >= 0
            ? digitsOnly.slice(dialCodeIndex + dialCode.length)
            : digitsOnly;

    const expectedLength = getLocalExampleNumberLength(iti);
    const isValid = iti.isValidNumber() && localNumber.length === expectedLength;

    input.classList.toggle("is-valid", isValid);
    input.classList.toggle("is-invalid", !isValid);

    return isValid;
}

document.addEventListener("DOMContentLoaded", () => {
    initializeIntlTelInput();

    document.addEventListener("input", function (e) {
        if (e.target.matches('input[type="tel"]')) {
            const input = e.target;
            const iti = itiInstances.get(input);
            if (!iti) return;

            const inputVal = keepOnlyNumbers(input.value);
            const dialCode = iti.getSelectedCountryData().dialCode;
            const expectedLength = getLocalExampleNumberLength(iti);

            // Extract local part (remove dial code if present)
            const dialCodeIndex = inputVal.indexOf(dialCode);
            let localPart =
                dialCodeIndex >= 0
                    ? inputVal.slice(dialCodeIndex + dialCode.length)
                    : inputVal;

            // Limit only if too long
            if (localPart.length > expectedLength) {
                localPart = localPart.slice(0, expectedLength + 4);
                input.value = localPart; // update field only when needed
            }

            // Update hidden input fields
            const hiddenInput = input.parentNode.querySelector('input[type="hidden"]:not([name="country_code"])');
            if (hiddenInput && iti) {
                hiddenInput.value = iti.getNumber();
            }

            const hiddenCountryCodeInput = input.parentNode.querySelector('input[name="country_code"]');
            if (hiddenCountryCodeInput && iti) {
                hiddenCountryCodeInput.value = iti.getSelectedCountryData()?.dialCode || "";
            }

            validatePhoneInput(input);
        }
    });

    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('reset', function () {
            setTimeout(() => {
                const phoneInputs = form.querySelectorAll('input[type="tel"]');

                phoneInputs.forEach(input => {
                    const iti = itiInstances.get(input);
                    if (!iti) return;

                    // Read from data-* attributes
                    const dataValue = input.getAttribute('data-value') || "";
                    const dataCountry = input.getAttribute('data-country') || "bd";

                    if (dataValue) {
                        iti.setCountry(dataCountry);       // set country first
                        iti.setNumber(dataValue);          // set full number
                        input.value = iti.getNumber();     // update visible input
                    } else {
                        iti.setCountry(dataCountry);
                        iti.setNumber("");
                        input.value = "";
                    }

                    // Update hidden input
                    const hiddenInput = input.parentNode.querySelector('input[type="hidden"]:not([name="country_code"])');
                    if (hiddenInput) hiddenInput.value = iti.getNumber();

                    // Update hidden country code input
                    const hiddenCountryCodeInput = input.parentNode.querySelector('input[name="country_code"]');
                    if (hiddenCountryCodeInput) hiddenCountryCodeInput.value = iti.getSelectedCountryData()?.dialCode || "";

                    // Re-set data-* attributes from current input state
                    input.setAttribute('data-value', iti.getNumber());
                    input.setAttribute('data-country', iti.getSelectedCountryData()?.iso2 || "");

                    input.classList.remove("is-valid", "is-invalid");
                });
            }, 0); // Wait for native reset to happen
        });
    });
});


try {
    // observer to watch for added inputs
    const observer = new MutationObserver(function(mutationsList) {
        mutationsList.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // element
                    if (node.matches('input[type="tel"]')) {
                        initIntlTelInputOn(node);
                    } else {
                        // also check descendants
                        node.querySelectorAll && node.querySelectorAll('input[type="tel"]').forEach(initIntlTelInputOn);
                    }
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });

    function initIntlTelInputOn(input) {
        if (!input.hasAttribute('data-intl-initialized')) {
            initializeIntlTelInput();
        }
    }

} catch (e) {

}
