document.addEventListener('DOMContentLoaded', function () {
    const membersContainer = document.getElementById('membersContainer');
    const addMemberBtn = document.getElementById('addMemberBtn');
    const memberTemplate = document.getElementById('memberTemplate');
    const form = document.getElementById('householdForm');
    const totalBeneficiaries = document.getElementById('totalBeneficiaries');
    const headLnameInput = document.querySelector('input[name="members[0][lname]"]');
    const householdNameInput = document.getElementById('householdName');
    const spouseFnameInput = document.querySelector('input[name="members[1][fname]"]');

    // Initialize with 3 default members (Head, Spouse, Child)
    const DEFAULT_MEMBER_COUNT = 3;
    let memberCount = DEFAULT_MEMBER_COUNT; // Your original initialization

    // --- START: Functions for Title Case visual formatting ---
    function toTitleCase(str) {
        if (!str) return '';
        // This regex handles words separated by spaces and also things like "O'Malley" or "Mary-Anne"
        // It capitalizes the first letter of each "word" segment after converting the whole string to lowercase.
        return str.toLowerCase().replace(/\b([a-z])|(-\w)/g, function (match) {
            return match.toUpperCase();
        }).replace(/\s+/g, ' '); // Normalize multiple spaces to one
    }

    function applyTitleCaseToNameField(inputElement) {
        if (inputElement) {
            const eventHandler = function () {
                if (!this.readOnly) { // Only apply if the field is not readonly
                    const originalValue = this.value;
                    const currentCursorPosition = this.selectionStart;
                    const currentCursorEnd = this.selectionEnd;

                    const newValue = toTitleCase(originalValue);

                    if (newValue !== originalValue) {
                        this.value = newValue;
                        // Try to restore cursor position, works best with 'input' event
                        // For 'blur', cursor position is not relevant as field loses focus
                        if (event && event.type === 'input') {
                            this.setSelectionRange(currentCursorPosition, currentCursorEnd);
                        }
                    }
                }
            };
            inputElement.addEventListener('input', eventHandler);
            inputElement.addEventListener('blur', eventHandler); // Also apply on blur for final formatting
        }
    }
    // --- END: Functions for Title Case visual formatting ---

    if (householdNameInput) {
        householdNameInput.readOnly = true;
    }

    function updateCountAndNames() {
        if (totalBeneficiaries) {
            totalBeneficiaries.value = document.querySelectorAll('.member-form').length;
        }

        // Auto-fill household name (Spouse's first name + Head's last name)
        // Apply Title Case to the components before combining
        let spouseFirstNameDisplay = '';
        let headLastNameDisplay = '';

        if (spouseFnameInput && spouseFnameInput.value) {
            spouseFirstNameDisplay = toTitleCase(spouseFnameInput.value);
        }
        if (headLnameInput && headLnameInput.value) {
            headLastNameDisplay = toTitleCase(headLnameInput.value);
        }

        if (householdNameInput) {
            if (spouseFirstNameDisplay && headLastNameDisplay) {
                householdNameInput.value = `${spouseFirstNameDisplay} ${headLastNameDisplay}`;
            } else if (headLastNameDisplay) { // Fallback if only head's last name is available
                householdNameInput.value = `Familia ${headLastNameDisplay}`;
            } else {
                householdNameInput.value = ''; // Clear if no names
            }
        }


        // Force-update all inherited last names with Title Cased version
        if (headLnameInput && headLnameInput.value) {
            // The headLnameInput itself gets title-cased by its own event listener.
            // Here, we ensure the value being propagated is the (already) title-cased one.
            const headLastNameForPropagation = headLnameInput.value; // Assumes headLnameInput is already title-cased by its listener
            document.querySelectorAll('.member-form:not(:first-child) input[name*="[lname]"].inherited-lname').forEach(el => {
                el.value = headLastNameForPropagation;
            });
        }
    }

    function calculateAge(birthDateString) { // Expects YYYY-MM-DD string
        if (!birthDateString) return ''; // Handle empty string
        const birthDate = new Date(birthDateString);
        // Check if the date is valid after parsing
        if (isNaN(birthDate.getTime())) {
            return ''; // Return empty for invalid date strings
        }

        const today = new Date();
        let age = today.getFullYear() - birthDate.getFullYear();
        const m = today.getMonth() - birthDate.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age >= 0 ? age : ''; // Return empty if age is negative (e.g., future date)
    }

    function setupAgeCalculator(memberForm) {
        const dateInput = memberForm.querySelector('input[type="date"]');
        const ageDisplay = memberForm.querySelector('.age-display');
        if (dateInput && ageDisplay) {
            const updateAge = () => { // Encapsulate logic
                if (dateInput.value) {
                    ageDisplay.value = calculateAge(dateInput.value);
                } else {
                    ageDisplay.value = '';
                }
            };
            dateInput.addEventListener('change', updateAge);
            // Also calculate on initial load if value is pre-filled (e.g., by PHP from session data)
            if (dateInput.value) {
                updateAge();
            }
        }
    }

    function addMember() {
        if (!memberTemplate || !membersContainer || !headLnameInput) {
            console.error('Required elements for adding member not found!');
            return;
        }

        const clone = memberTemplate.content.cloneNode(true);
        const memberForm = clone.querySelector('.member-form');
        const newIndex = memberCount++; // Your original indexing logic

        memberForm.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace('members[]', `members[${newIndex}]`);
        });

        // *** Apply Title Case to newly added member's editable name fields ***
        applyTitleCaseToNameField(memberForm.querySelector('input[name*="[fname]"]'));
        applyTitleCaseToNameField(memberForm.querySelector('input[name*="[mname]"]'));
        // Lname for new child is readonly and inherits from Head's lname,
        // which should already be title-cased by its own listener.

        const lnameInput = memberForm.querySelector('input[name*="[lname]"]');
        if (lnameInput && headLnameInput) { // Check headLnameInput before accessing its value
            lnameInput.value = headLnameInput.value; // Inherit (already title-cased by Head's input listener)
            lnameInput.readOnly = true;
            lnameInput.classList.add('inherited-lname');
        }

        const genderSelect = memberForm.querySelector('select[name*="[sex]"]');
        const relationshipInput = memberForm.querySelector('input[name*="[relationship]"]');
        const relationshipDisplay = memberForm.querySelector('.member-relationship'); // Your original selector

        function updateRelationship() {
            if (genderSelect && relationshipInput && relationshipDisplay) {
                const relationship = genderSelect.value === 'Male' ? 'Son' : 'Daughter';
                relationshipInput.value = relationship; // Set hidden input
                relationshipDisplay.textContent = relationship; // Update display span
            }
        }

        if (genderSelect) {
            updateRelationship();
            genderSelect.addEventListener('change', updateRelationship);
        }

        setupAgeCalculator(memberForm);

        const removeBtn = memberForm.querySelector('.btn-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                if (confirm('Remove this member?')) {
                    memberForm.remove();
                    memberCount--; // Your original decrement
                    updateCountAndNames();
                }
            });
        }

        membersContainer.appendChild(clone); // Your original append logic
        updateCountAndNames();
    }

    function configureDefaultMembers() {
        const memberForms = document.querySelectorAll('.member-form');
        memberForms.forEach((form, index) => {
            setupAgeCalculator(form);

            // *** Apply Title Case to initially rendered members' editable name fields ***
            applyTitleCaseToNameField(form.querySelector('input[name*="[fname]"]'));
            applyTitleCaseToNameField(form.querySelector('input[name*="[mname]"]'));
            if (index === 0) { // Only Head's last name is directly editable and needs this listener
                applyTitleCaseToNameField(form.querySelector('input[name*="[lname]"]'));
            }

            if (index < DEFAULT_MEMBER_COUNT) {
                const removeBtn = form.querySelector('.btn-remove');
                if (removeBtn) removeBtn.style.display = 'none';

                if (index > 0 && headLnameInput && headLnameInput.value) { // Spouse or initial Child
                    const lnameInput = form.querySelector('input[name*="[lname]"]');
                    if (lnameInput) {
                        lnameInput.readOnly = true;
                        lnameInput.classList.add('inherited-lname');
                        // Value set by updateCountAndNames or PHP repopulation (will be title-cased by headLnameInput's listener)
                        lnameInput.value = headLnameInput.value;
                    }
                }

                const sexSelect = form.querySelector('select[name*="[sex]"]');
                if (sexSelect) {
                    if (index === 0) { // Head
                        sexSelect.value = 'Male';
                        sexSelect.readOnly = true;
                        sexSelect.style.pointerEvents = 'none';
                        sexSelect.style.backgroundColor = '#eee';
                    } else if (index === 1) { // Spouse
                        sexSelect.value = 'Female';
                        sexSelect.readOnly = true;
                        sexSelect.style.pointerEvents = 'none';
                        sexSelect.style.backgroundColor = '#eee';
                    }
                }

                const occupationSelect = form.querySelector('select[name*="[occupation]"]');
                const incomeSelect = form.querySelector('select[name*="[income]"]');
                if (index > 1) { // Original default child (index 2)
                    if (occupationSelect) occupationSelect.disabled = true;
                    if (incomeSelect) incomeSelect.disabled = true;
                }
            }
        });
    }

    // Form validation (your original logic)
    if (form) {
        form.addEventListener('submit', function (e) {
            let isValid = true;
            let firstInvalidField = null;

            document.querySelectorAll('[required]').forEach(field => {
                field.style.borderColor = '';
            });

            document.querySelectorAll('[required]').forEach(field => {
                // Only validate if not disabled
                if (!field.disabled) {
                    if ((field.tagName === 'SELECT' && field.value === '') || (field.tagName !== 'SELECT' && !field.value.trim())) {
                        field.style.borderColor = '#e74c3c';
                        isValid = false;
                        if (!firstInvalidField) {
                            firstInvalidField = field;
                        }
                    }
                }
            });

            // Re-enable disabled fields before submission IF form is valid
            // Server-side should re-validate if these fields are conditionally required
            const disabledFieldsToSubmit = form.querySelectorAll('select:disabled, input:disabled');
            if (isValid) {
                disabledFieldsToSubmit.forEach(df => df.disabled = false);
            }


            if (!isValid) {
                e.preventDefault();
                alert('Please fill all required fields');
                if (firstInvalidField) {
                    firstInvalidField.focus();
                }
                // If validation failed, re-disable fields that were temporarily enabled for submission attempt
                // This is tricky; configureDefaultMembers might re-disable them correctly if called.
                // For now, if validation fails, it's safer to let them be as they were before this submit attempt.
                // The user will correct and resubmit. A call to configureDefaultMembers could be placed here if needed.
            }
        });
    }

    // Initialize
    configureDefaultMembers();
    updateCountAndNames();

    if (headLnameInput) {
        headLnameInput.addEventListener('input', updateCountAndNames);
    }
    if (spouseFnameInput) {
        spouseFnameInput.addEventListener('input', updateCountAndNames);
    }

    if (addMemberBtn) {
        addMemberBtn.addEventListener('click', addMember);
    }

    const barangayIDSelect = document.getElementById('barangayID');
    if (barangayIDSelect) {
        barangayIDSelect.addEventListener('change', function () {
            const barangayID = this.value;
            const householdIDInput = document.getElementById('householdID'); // Define it here
            if (householdIDInput) { // Check if householdIDInput exists
                if (barangayID) {
                    fetch(`generate_householdID.php?barangayID=${encodeURIComponent(barangayID)}`)
                        .then(response => {
                            if (!response.ok) { // Better error handling for fetch
                                return response.text().then(text => { throw new Error(text || `Network response was not ok: ${response.status}`); });
                            }
                            return response.text();
                        })
                        .then(data => {
                            if (data && !data.toLowerCase().includes('error')) { // Check for 'error' case-insensitively
                                householdIDInput.value = data;
                            } else {
                                console.error('Error generating household ID from server:', data);
                                householdIDInput.value = '';
                                alert('Could not generate Household ID: ' + data); // Inform user
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching household ID:', error);
                            householdIDInput.value = '';
                            alert('Error fetching Household ID: ' + error.message); // Inform user
                        });
                } else {
                    householdIDInput.value = '';
                }
            }
        });
        // If barangay is pre-selected (e.g. form repopulation via phpFormData) and HHID is empty, generate it
        // This check assumes phpFormData is available globally if this script is included after phpFormData is defined
        if (typeof phpFormData !== 'undefined' && phpFormData.barangayID && barangayIDSelect.value === phpFormData.barangayID) {
            const householdIDInput = document.getElementById('householdID');
            if (householdIDInput && !householdIDInput.value) { // Only if HHID is empty
                barangayIDSelect.dispatchEvent(new Event('change'));
            }
        } else if (barangayIDSelect.value && document.getElementById('householdID') && !document.getElementById('householdID').value) {
            // General case if barangay is selected but HHID is empty on load
            barangayIDSelect.dispatchEvent(new Event('change'));
        }
    }


    // Update memberCount based on how many forms PHP actually rendered
    memberCount = document.querySelectorAll('.member-form').length;

    // If PHP used $formData to render more than DEFAULT_MEMBER_COUNT, 
    // the JavaScript doesn't need to dynamically add them again here using the template,
    // as they should already be in the DOM.
    // The crucial part is that `configureDefaultMembers()` runs on ALL existing .member-form elements
    // to attach listeners, and `updateCountAndNames()` correctly reflects the state.

    // If form data was used by PHP to populate, re-run these to ensure JS state is synced
    if (typeof phpFormData !== 'undefined' && Object.keys(phpFormData).length > 0) {
        configureDefaultMembers(); // This will attach TitleCase listeners to all pre-filled name fields
        updateCountAndNames();     // This will update counts and household name based on pre-filled values
    }
});
