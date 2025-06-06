// js/editHousehold.js

// Function to handle household deletion
function confirmDelete(householdID, householdName) {
    if (confirm(`Are you sure you want to delete household "${householdName}" (ID: ${householdID})?\nThis action cannot be undone and will remove all associated members.`)) {

        // Use the global CSRF token defined in editHousehold.php
        if (typeof csrfTokenForDelete === 'undefined' || !csrfTokenForDelete) {
            alert('Error: CSRF token for deletion is missing. Please refresh the page and try again.');
            console.error("JS Error: Global csrfTokenForDelete is not defined or empty.");
            return;
        }
        const csrfToken = csrfTokenForDelete;

        const formData = new FormData();
        formData.append('householdID', householdID);
        formData.append('csrf_token', csrfToken); // Ensure this name matches what deletehousehold.php expects

        console.log(`JS: Attempting to delete household ID: ${householdID}`);

        fetch('php/deletehousehold.php', { // UPDATED PATH to your new delete script
            method: 'POST',
            body: formData
        })
            .then(response => {
                console.log("JS: Response from php/deletehousehold.php. Status:", response.status, "Ok:", response.ok);
                if (!response.ok) {
                    return response.json().then(errData => {
                        throw new Error(errData.message || `Server error: ${response.status}. Please check server logs.`);
                    }).catch(() => {
                        throw new Error(`Server error: ${response.status} ${response.statusText}. The server response was not in the expected JSON format.`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log("JS: Parsed server data from php/deletehousehold.php JSON:", data);
                alert(data.message);
                if (data.success) {
                    location.reload(); // Reloads the page to reflect the deletion
                }
            })
            .catch(error => {
                console.error('JS: Deletion request failed or error processing response:', error);
                alert('An error occurred while trying to delete the household: ' + error.message);
            });
    }
}


document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('editModal');
    if (!modal) {
        console.error("JS Error: Modal element with ID 'editModal' not found. Edit functionality disabled.");
        return;
    }
    const modalFormContent = document.getElementById('modalFormContent');
    const mainCloseBtn = modal.querySelector('.modal-close');
    let currentSearchTerm = new URLSearchParams(window.location.search).get('search') || '';

    // Helper function to navigate with notification parameters
    function navigateWithNotificationParams(status, message) {
        const targetUrl = new URL(window.location.pathname, window.location.origin);

        const existingSearchParams = new URLSearchParams(window.location.search);
        if (existingSearchParams.has('search')) {
            currentSearchTerm = existingSearchParams.get('search');
            targetUrl.searchParams.set('search', currentSearchTerm);
        } else {
            currentSearchTerm = '';
        }

        targetUrl.searchParams.delete('status');
        targetUrl.searchParams.delete('msg');

        targetUrl.searchParams.set('status', status);
        targetUrl.searchParams.set('msg', encodeURIComponent(message));

        console.log("JS: Navigating to with notification:", targetUrl.toString());
        window.location.href = targetUrl.toString();
    }

    document.querySelectorAll('.btn-edit').forEach(button => {
        button.addEventListener('click', function () {
            const householdId = this.dataset.householdId;
            currentSearchTerm = new URLSearchParams(window.location.search).get('search') || '';

            if (!householdId) {
                console.error("JS Error: Household ID not found on button dataset.");
                alert("Could not get Household ID.");
                return;
            }

            console.log(`JS: Opening modal for householdID: ${householdId}`);
            modalFormContent.innerHTML = '<p style="text-align:center; padding:40px;">Loading household data...</p>';
            modal.style.display = 'block';

            fetch(`edit_modal.php?householdID=${encodeURIComponent(householdId)}`)
                .then(response => {
                    console.log("JS: Fetched edit_modal.php. Status:", response.status);
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error("JS Error: response text from edit_modal.php:", text);
                            throw new Error(`HTTP error fetching form! Status: ${response.status} - ${text || response.statusText}`);
                        });
                    }
                    return response.text();
                })
                .then(html => {
                    console.log("JS: Received HTML for modal form. Injecting into modalFormContent.");
                    modalFormContent.innerHTML = html;
                    initializeModalFormScripting(modalFormContent); // This function should be defined below

                    const form = modalFormContent.querySelector('form#editHouseholdForm'); // Be specific with form ID
                    if (form) {
                        console.log("JS: Found form #editHouseholdForm in modal. Action URL:", form.action);
                        form.addEventListener('submit', function (event) {
                            event.preventDefault();
                            console.log("JS: Modal form 'submit' event triggered.");

                            const formData = new FormData(form);

                            console.log("JS: Attempting fetch POST to:", form.action); // Should be php/editprocess.php

                            fetch(form.action, {
                                method: 'POST',
                                body: formData
                            })
                                .then(response => {
                                    console.log("JS: Response from server. Status:", response.status, "Ok:", response.ok);
                                    return response.json().then(data => ({
                                        http_status: response.status,
                                        http_ok: response.ok,
                                        data_from_json: data
                                    }));
                                })
                                .then(result => {
                                    const { data_from_json } = result;
                                    console.log("JS: Parsed server data from JSON:", data_from_json);
                                    closeModal();

                                    let message_text = "An unexpected response occurred.";
                                    let status_param = "error";

                                    if (data_from_json) {
                                        status_param = data_from_json.success ? 'success' : 'error';
                                        message_text = data_from_json.message || (data_from_json.success ? 'Operation completed successfully.' : 'Operation failed.');
                                    } else {
                                        message_text = "Received an empty or invalid response from the server.";
                                    }

                                    console.log("JS: Message for URL:", message_text, "| Status for URL:", status_param);
                                    navigateWithNotificationParams(status_param, message_text);
                                })
                                .catch(error => {
                                    console.error("JS: Critical error in fetch/JSON parsing OR server sent non-JSON:", error);
                                    closeModal();
                                    let errorMsgForUrl = 'A critical error occurred during the update. Please check the console.';
                                    if (error && error.message) {
                                        errorMsgForUrl = error.message;
                                    }
                                    console.log("JS: Critical error message for URL:", errorMsgForUrl);
                                    navigateWithNotificationParams('error', errorMsgForUrl);
                                });
                        });
                    } else {
                        console.error("JS Error: Form element #editHouseholdForm not found within modalFormContent.");
                        modalFormContent.innerHTML = "<p style='color:red; text-align:center;'>Error: The edit form structure is missing.</p>";
                    }
                })
                .catch(error => {
                    console.error('JS Error: Failed to fetch or display edit_modal.php HTML:', error);
                    modalFormContent.innerHTML = `<div class="alert-notification error" role="alert">Error loading edit form: ${error.message}.</div><div style="text-align:center; margin-top:15px;"><button type="button" class="btn btn-cancel modal-dynamic-close">Close</button></div>`;
                    const dynCloseBtn = modalFormContent.querySelector('.modal-dynamic-close');
                    if (dynCloseBtn) dynCloseBtn.addEventListener('click', closeModal);
                });
        });
    });

    function closeModal() {
        if (modal) modal.style.display = 'none';
        if (modalFormContent) modalFormContent.innerHTML = '';
    }

    if (mainCloseBtn) mainCloseBtn.addEventListener('click', closeModal);

    window.addEventListener('click', function (event) {
        if (event.target == modal) closeModal();
    });

    if (modalFormContent) {
        modalFormContent.addEventListener('click', function (event) {
            if (event.target.classList.contains('modal-close-button') || event.target.classList.contains('modal-dynamic-close')) {
                closeModal();
            }
        });
    }

    // --- YOUR EXISTING HELPER FUNCTIONS from editHousehold.js ---
    // Make sure initializeModalFormScripting and its dependent functions are defined correctly.
    // The following functions are assumed to be part of your original script or defined here.

    function initializeModalFormScripting(modalContentElement) {
        console.log("JS: Initializing scripts for modal content (initializeModalFormScripting function)...");

        const editMembersContainer = modalContentElement.querySelector('#editMembersContainer');
        const editAddMemberBtn = modalContentElement.querySelector('#editAddMemberBtn');
        const editMemberTemplate = modalContentElement.querySelector('#editMemberTemplate');
        const editHeadLnameInput = modalContentElement.querySelector('input[name="members[0][lname]"]');
        const hiddenDeletedInput = modalContentElement.querySelector('#deleted_members'); // Ensure this hidden input exists in edit_modal.php

        modalContentElement.querySelectorAll('.member-form').forEach(memberForm => {
            setupAgeCalculatorForForm(memberForm);
            setupRelationshipUpdater(memberForm);
        });

        if (editAddMemberBtn && editMemberTemplate && editMembersContainer && editHeadLnameInput && hiddenDeletedInput) {
            editAddMemberBtn.addEventListener('click', function () {
                console.log("JS DEBUG: 'Add Child/Member' button clicked.");
                const clone = editMemberTemplate.content.cloneNode(true);
                const newMemberForm = clone.querySelector('.member-form');

                const existingForms = editMembersContainer.querySelectorAll('.member-form');
                let maxIndex = -1;
                existingForms.forEach(form => {
                    const formIndexAttr = form.dataset.index; // Get the string value
                    if (formIndexAttr !== undefined && formIndexAttr !== null) {
                        const formIndex = parseInt(formIndexAttr, 10);
                        if (!isNaN(formIndex) && formIndex > maxIndex) {
                            maxIndex = formIndex;
                        }
                    }
                });
                const currentIndex = maxIndex + 1;
                newMemberForm.dataset.index = currentIndex;

                newMemberForm.querySelectorAll('[name*="__INDEX__"]').forEach(el => {
                    el.name = el.name.replace(/__INDEX__/g, currentIndex);
                    if (el.id) el.id = el.id.replace(/__INDEX__/g, currentIndex);
                });
                // Ensure labels 'for' attributes are also updated if they use __INDEX__
                newMemberForm.querySelectorAll('label[for*="__INDEX__"]').forEach(label => {
                    label.htmlFor = label.htmlFor.replace(/__INDEX__/g, currentIndex);
                });

                const lnameInput = newMemberForm.querySelector('input[name$="[lname]"]'); // More specific selector
                if (lnameInput && editHeadLnameInput) lnameInput.value = editHeadLnameInput.value;

                setupAgeCalculatorForForm(newMemberForm);
                setupRelationshipUpdater(newMemberForm);

                const removeBtn = newMemberForm.querySelector('.btn-remove-member.new-remove');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function () {
                        if (confirm('Remove this newly added member entry?')) {
                            newMemberForm.remove();
                            updateTotalBeneficiariesDisplay(modalContentElement);
                        }
                    });
                }
                editMembersContainer.appendChild(clone);
                updateTotalBeneficiariesDisplay(modalContentElement);
            });
        } else {
            console.warn("JS Warn: Edit Modal 'Add Member' dependencies not fully found (editAddMemberBtn, editMemberTemplate, editMembersContainer, editHeadLnameInput, or hiddenDeletedInput).");
        }

        modalContentElement.querySelectorAll('.existing-member .btn-remove-member').forEach(removeBtn => {
            removeBtn.addEventListener('click', function () {
                const memberForm = this.closest('.member-form');
                const memberIdToRemove = memberForm.dataset.memberId; // Ensure data-member-id attribute is on .member-form
                const memberNameEl = memberForm.querySelector('input[name*="[fname]"]');
                const memberLnameEl = memberForm.querySelector('input[name*="[lname]"]');
                const memberName = (memberNameEl ? memberNameEl.value : 'Member') + ' ' + (memberLnameEl ? memberLnameEl.value : '');

                if (memberIdToRemove && confirm(`Are you sure you want to mark ${memberName.trim()} (ID: ${memberIdToRemove}) for deletion?\nThis will be permanent upon saving changes.`)) {
                    memberForm.style.display = 'none';
                    memberForm.dataset.markedForDeletion = "true"; // Mark it visually/logically

                    // Add memberIdToRemove to the hidden input #deleted_members
                    if (hiddenDeletedInput) {
                        let currentDeleted = hiddenDeletedInput.value ? hiddenDeletedInput.value.split(',').filter(id => id.trim() !== '') : [];
                        if (!currentDeleted.includes(memberIdToRemove)) {
                            currentDeleted.push(memberIdToRemove);
                            hiddenDeletedInput.value = currentDeleted.join(',');
                            console.log("JS DEBUG: Added to #deleted_members input:", hiddenDeletedInput.value);
                        }
                    } else {
                        console.error("JS Error: Hidden input #deleted_members not found for existing members!");
                    }
                    updateTotalBeneficiariesDisplay(modalContentElement);
                }
            });
        });
        updateTotalBeneficiariesDisplay(modalContentElement); // Initial count

        const hhNameInput = modalContentElement.querySelector('#edit_householdName');
        const spouseFNameInput = modalContentElement.querySelector('input[name="members[1][fname]"]');
        const headLNameInputForNameGen = modalContentElement.querySelector('input[name="members[0][lname]"]');
        if (hhNameInput && headLNameInputForNameGen) {
            if (spouseFNameInput) {
                hhNameInput.readOnly = true;
                hhNameInput.style.backgroundColor = '#eee';
            }
            // Ensure hh_name_updater is called after inputs are potentially populated
            setTimeout(() => hh_name_updater(hhNameInput, headLNameInputForNameGen, spouseFNameInput), 0);

            if (headLNameInputForNameGen) headLNameInputForNameGen.addEventListener('input', () => hh_name_updater(hhNameInput, headLNameInputForNameGen, spouseFNameInput));
            if (spouseFNameInput) spouseFNameInput.addEventListener('input', () => hh_name_updater(hhNameInput, headLNameInputForNameGen, spouseFNameInput));
        }
    } // End initializeModalFormScripting

    function setupAgeCalculatorForForm(memberFormElement) {
        const dateInput = memberFormElement.querySelector('input[type="date"][name*="[bday]"]');
        const ageDisplay = memberFormElement.querySelector('.age-display'); // Assuming an input field for age: <input type="text" class="age-display" readonly>
        if (dateInput && ageDisplay) {
            const calculateAndDisplayAge = () => {
                if (dateInput.value) {
                    try {
                        const birthDate = new Date(dateInput.value);
                        if (isNaN(birthDate.getTime())) {
                            console.warn("JS Warn: Invalid date entered:", dateInput.value);
                            ageDisplay.value = ''; return;
                        }
                        const calculatedAge = calculateAge(birthDate); // calculateAge defined below
                        ageDisplay.value = calculatedAge;
                    } catch (e) { console.error("JS Error calculating age:", e); ageDisplay.value = ''; }
                } else {
                    ageDisplay.value = '';
                }
            };
            dateInput.addEventListener('change', calculateAndDisplayAge);
            dateInput.addEventListener('input', calculateAndDisplayAge);
            if (dateInput.value) calculateAndDisplayAge(); // Initial calculation if date is pre-filled
        }
    }

    function setupRelationshipUpdater(memberFormElement) {
        const genderSelect = memberFormElement.querySelector('select[name*="[sex]"]');
        // The actual input that stores 'Son' or 'Daughter'
        const relationshipValueInput = memberFormElement.querySelector('input[type="hidden"][name*="[relationship]"], select[name*="[relationship]"]');
        // The text display for the relationship (e.g., <h3>Child (<span class="member-child-type-span">Son</span>)</h3>)
        const relationshipDisplayTextElement = memberFormElement.querySelector('.member-relationship'); // e.g., the <h3>
        const childTypeSpan = relationshipDisplayTextElement ? relationshipDisplayTextElement.querySelector('.member-child-type-span') : null; // The <span> inside the <h3>

        if (genderSelect && relationshipValueInput && relationshipDisplayTextElement) {
            const updateRel = () => {
                const currentRelValue = relationshipValueInput.value;
                // Only auto-update if the current value suggests it's a 'Child' type.
                // The form should ideally set the initial relationshipValueInput to 'Child' for new children.
                if (currentRelValue === 'Child' || currentRelValue === 'Son' || currentRelValue === 'Daughter') {
                    const newRelType = genderSelect.value === 'Male' ? 'Son' : 'Daughter';
                    relationshipValueInput.value = newRelType;
                    if (childTypeSpan) {
                        childTypeSpan.textContent = newRelType;
                    } else if (relationshipDisplayTextElement.textContent.toLowerCase().includes('child')) {
                        // Fallback if span isn't there but text indicates "Child"
                        relationshipDisplayTextElement.textContent = 'Child (' + newRelType + ')';
                    }
                    console.log("JS: Relationship for child updated in form to:", newRelType);
                }
            };
            genderSelect.addEventListener('change', updateRel);
            // Initial call if relationship is 'Child' to set Son/Daughter based on pre-selected gender
            if (relationshipValueInput.value === 'Child') {
                updateRel();
            }
        }
    }

    function updateTotalBeneficiariesDisplay(modalContentElement) {
        const countInput = modalContentElement.querySelector('#edit_totalBeneficiaries'); // Input field in the modal
        if (countInput) {
            // Count member forms that are not styled with display:none (i.e., not "deleted" visually)
            const visibleMembers = modalContentElement.querySelectorAll('#editMembersContainer .member-form:not([style*="display: none"]):not([data-marked-for-deletion="true"])');
            countInput.value = visibleMembers.length;
            console.log("JS: Total visible beneficiaries in modal updated to:", visibleMembers.length);
        }
    }

    function hh_name_updater(hhNameInput, headLNameInput, spouseFNameInput) {
        if (hhNameInput && headLNameInput && headLNameInput.value) {
            if (spouseFNameInput && spouseFNameInput.value) { // If there is a spouse with a first name
                hhNameInput.value = `${spouseFNameInput.value} ${headLNameInput.value}`;
            } else if (!spouseFNameInput || !spouseFNameInput.value) { // If there is no spouse input, or spouse has no first name
                // You might want a different logic if spouseFNameInput exists but is empty
                // For now, if spouseFNameInput is not there or is empty, default to "HeadLName Household"
                // hhNameInput.value = `${headLNameInput.value} Household`; // Or clear it, or let user type
            }
        }
    }

    function calculateAge(birthDate) {
        if (!(birthDate instanceof Date) || isNaN(birthDate.getTime())) {
            return ''; // Invalid date object
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Normalize today's date to midnight for accurate age

        const birthDateOnly = new Date(birthDate.getFullYear(), birthDate.getMonth(), birthDate.getDate());

        if (birthDateOnly > today) { // Birthdate in the future
            return '';
        }

        let age = today.getFullYear() - birthDateOnly.getFullYear();
        const monthDiff = today.getMonth() - birthDateOnly.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDateOnly.getDate())) {
            age--;
        }
        return age >= 0 ? age : 0; // Return 0 if calculated age is negative (shouldn't happen with check above)
    }

}); // End DOMContentLoaded