// script.js

const API_BASE_URL = 'admin_api.php';

// --- DOM Elements ---
const loginSection = document.getElementById('loginSection');
const adminPanelSections = document.getElementById('adminPanelSections');
const adminKeyInput = document.getElementById('adminKeyInput');
const loginBtn = document.getElementById('loginBtn');
const logoutBtn = document.getElementById('logoutBtn');
const loginStatus = document.getElementById('loginStatus');

const sectionNameInput = document.getElementById('sectionNameInput');
const addSectionBtn = document.getElementById('addSectionBtn');
const updateSectionBtn = document.getElementById('updateSectionBtn');
const deleteSectionBtn = document.getElementById('deleteSectionBtn');
const sectionStatus = document.getElementById('sectionStatus');

const unityPackageFileInput = document.getElementById('unityPackageFile');
const uploadFileBtn = document.getElementById('uploadFileBtn');
const uploadStatus = document.getElementById('uploadStatus');

const resourceNameInput = document.getElementById('resourceName');
const resourceURLInput = document.getElementById('resourceURL');
const accessLevelInput = document.getElementById('accessLevel');
const buttonLabelInput = document.getElementById('buttonLabel');
const sectionSelect = document.getElementById('sectionSelect');
const addOrUpdateResourceBtn = document.getElementById('addOrUpdateResourceBtn');
const deleteResourceBtn = document.getElementById('deleteResourceBtn');
const resourceStatus = document.getElementById('resourceStatus');
const resourcesListDiv = document.getElementById('resourcesList');

// --- Global State ---
let currentResourcesData = null;
let selectedResourceName = null;
let selectedResourceSectionIndex = null;
let selectedResourceButtonIndex = null;
let selectedSectionIndexForEdit = null; // For section management

// --- Helper Functions ---
function showStatus(element, message, isError = false) {
    element.textContent = message;
    element.className = '';
    if (message) {
        element.style.backgroundColor = isError ? '#f8d7da' : '#d4edda';
        element.style.color = isError ? '#721c24' : '#155724';
    } else {
        element.style.backgroundColor = '';
        element.style.color = '';
    }
}

function clearForm() {
    resourceNameInput.value = '';
    resourceURLInput.value = '';
    accessLevelInput.value = '1';
    buttonLabelInput.value = '';
    selectedResourceName = null;
    selectedResourceSectionIndex = null;
    selectedResourceButtonIndex = null;
    deleteResourceBtn.style.display = 'none';
    addOrUpdateResourceBtn.textContent = 'Add/Update Resource';
    showStatus(resourceStatus, '');
    showStatus(uploadStatus, '');
    document.querySelectorAll('.resource-item.selected').forEach(item => {
        item.classList.remove('selected');
    });
    // Clear section form
    sectionNameInput.value = '';
    selectedSectionIndexForEdit = null;
    updateSectionBtn.style.display = 'none';
    deleteSectionBtn.style.display = 'none';
    addSectionBtn.textContent = 'Add New Section';
    showStatus(sectionStatus, '');
    document.querySelectorAll('.section-header.selected').forEach(item => {
        item.classList.remove('selected');
    });
}

function showAdminPanel(isLoggedIn) {
    if (isLoggedIn) {
        loginSection.style.display = 'none';
        adminPanelSections.style.display = 'block';
    } else {
        loginSection.style.display = 'block';
        adminPanelSections.style.display = 'none';
    }
}

async function fetchData(action, method = 'GET', data = null) {
    let url = `${API_BASE_URL}?action=${action}`;
    const options = { method: method };

    const adminKey = adminKeyInput.value.trim();
    if (!adminKey) {
        throw new Error('Admin Key is required for this action.');
    }

    if (method === 'POST') {
        if (data instanceof FormData) {
            data.append('admin_key', adminKey);
            options.body = data;
        } else {
            options.headers = {
                'Content-Type': 'application/json',
                'X-Admin-Key': adminKey
            };
            options.body = JSON.stringify(data);
        }
    } else {
        options.headers = {
            'X-Admin-Key': adminKey
        };
    }

    try {
        console.log(`Fetching: ${url} with method ${method}`);
        if (method === 'POST' && !(data instanceof FormData)) {
            console.log('Request Payload:', JSON.parse(options.body));
        }
        console.log('Request Headers:', options.headers);

        const response = await fetch(url, options);
        const result = await response.json();
        console.log('Response Status:', response.status);
        console.log('Response Body:', result);

        if (!response.ok) {
            const errorMsg = result.error || `HTTP Error! Status: ${response.status}`;
            throw new Error(errorMsg);
        }
        return result;
    } catch (error) {
        console.error('API Call Error:', error);
        throw error;
    }
}

async function handleLogin() {
    const key = adminKeyInput.value.trim();
    if (!key) {
        showStatus(loginStatus, 'Please enter your admin key.', true);
        return;
    }

    showStatus(loginStatus, 'Attempting login...', false);

    try {
        currentResourcesData = await fetchData('get_data');
        showStatus(loginStatus, 'Login successful!', false);
        showAdminPanel(true);
        loadResources(); // Also loads sections
    } catch (error) {
        showStatus(loginStatus, `Login failed: ${error.message}. Please check your Admin Key.`, true);
        showAdminPanel(false);
    }
}

function handleLogout() {
    adminKeyInput.value = '';
    showStatus(loginStatus, 'Logged out.');
    showAdminPanel(false);
    clearForm();
    resourcesListDiv.innerHTML = '<p>Resources will load here after successful login.</p>';
    currentResourcesData = null;
}

async function loadResources() {
    try {
        currentResourcesData = await fetchData('get_data');

        resourcesListDiv.innerHTML = '';
        sectionSelect.innerHTML = ''; // Clear existing sections dropdown

        if (currentResourcesData.buttons && currentResourcesData.buttons.length > 0) {
            currentResourcesData.buttons.forEach((section, index) => {
                const option = document.createElement('option');
                option.value = index;
                option.textContent = section.section;
                sectionSelect.appendChild(option);
            });
            sectionSelect.disabled = false;
        } else {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No sections available';
            sectionSelect.appendChild(option);
            sectionSelect.disabled = true;
        }

        if (!currentResourcesData.resources || Object.keys(currentResourcesData.resources).length === 0) {
            // resourcesListDiv.textContent = 'No resources configured yet.'; // Commented out as sections will still show
        }

        if (!currentResourcesData.buttons || currentResourcesData.buttons.length === 0) {
            const noButtonsMessage = document.createElement('p');
            noButtonsMessage.textContent = 'No button sections configured in resources_and_buttons.json.';
            resourcesListDiv.appendChild(noButtonsMessage);
        }

        currentResourcesData.buttons.forEach((section, sectionIndex) => {
            const sectionHeader = document.createElement('h3');
            sectionHeader.textContent = section.section;
            sectionHeader.classList.add('section-header');
            sectionHeader.dataset.sectionIndex = sectionIndex; // Add data attribute for click handling
            resourcesListDiv.appendChild(sectionHeader);

            // Add event listener for clicking on section header to edit
            sectionHeader.addEventListener('click', () => {
                document.querySelectorAll('.section-header').forEach(item => {
                    item.classList.remove('selected');
                });
                sectionHeader.classList.add('selected');

                selectedSectionIndexForEdit = sectionIndex;
                sectionNameInput.value = section.section;
                updateSectionBtn.style.display = 'inline-block';
                deleteSectionBtn.style.display = 'inline-block';
                addSectionBtn.textContent = 'Add New Section (Clear)'; // Change button text to indicate clear form
            });

            const buttonsContainer = document.createElement('div');
            buttonsContainer.style.display = 'flex';
            buttonsContainer.style.flexWrap = 'wrap';
            buttonsContainer.style.gap = '10px';
            resourcesListDiv.appendChild(buttonsContainer);

            section.buttons.forEach((button, buttonIndex) => {
                const resourceItem = document.createElement('div');
                resourceItem.classList.add('resource-item');
                resourceItem.style.width = 'calc(33.333% - 10px)'; // For 3 columns in admin panel

                let displayLabel = button.label;
                let displayName = button.name;
                let accessLevelDisplay = 'N/A';
                let isClickable = true;

                if (button.name === "Empty") {
                    displayLabel = "Empty Slot";
                    displayName = "";
                    resourceItem.classList.add('empty-slot');
                    isClickable = true; // Empty slots should be clickable to add new resource
                } else {
                    accessLevelDisplay = currentResourcesData.resources[button.name]?.access_level || 'N/A';
                }

                resourceItem.innerHTML = `
                    <span>
                        <strong>Label:</strong> ${displayLabel}
                        ${displayName ? ` | <strong>Resource:</strong> ${displayName}` : ''}
                        ${accessLevelDisplay !== 'N/A' ? ` | <strong>Access:</strong> ${accessLevelDisplay}` : ''}
                    </span>
                `;
                resourceItem.dataset.resourceName = button.name;
                resourceItem.dataset.sectionIndex = sectionIndex;
                resourceItem.dataset.buttonIndex = buttonIndex;

                if (isClickable) {
                    resourceItem.addEventListener('click', () => {
                        document.querySelectorAll('.resource-item').forEach(item => {
                            item.classList.remove('selected');
                        });
                        resourceItem.classList.add('selected');

                        selectedResourceName = button.name;
                        selectedResourceSectionIndex = sectionIndex;
                        selectedResourceButtonIndex = buttonIndex;

                        resourceNameInput.value = button.name !== "Empty" ? button.name : '';
                        buttonLabelInput.value = button.label !== "Empty Slot" ? button.label : '';
                        resourceURLInput.value = (button.name !== "Empty" && currentResourcesData.resources[button.name]) ? currentResourcesData.resources[button.name].url : '';
                        accessLevelInput.value = (button.name !== "Empty" && currentResourcesData.resources[button.name]) ? currentResourcesData.resources[button.name].access_level : '1';
                        sectionSelect.value = sectionIndex;
                        addOrUpdateResourceBtn.textContent = 'Update Resource';
                        deleteResourceBtn.style.display = 'inline-block';
                    });
                }
                buttonsContainer.appendChild(resourceItem);
            });
        });
        showStatus(resourcesListDiv, '', false);
    } catch (error) {
        showStatus(resourcesListDiv, `Error loading resources: ${error.message}. Make sure the JSON files exist and are valid.`, true);
        console.error("Failed to load resources:", error);
    }
}

// --- Event Listeners ---
loginBtn.addEventListener('click', handleLogin);
logoutBtn.addEventListener('click', handleLogout);
adminKeyInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
        handleLogin();
    }
});

addSectionBtn.addEventListener('click', async () => {
    const sectionName = sectionNameInput.value.trim();
    if (!sectionName) {
        showStatus(sectionStatus, 'Section name cannot be empty.', true);
        return;
    }

    if (selectedSectionIndexForEdit !== null) { // If a section is selected, clicking "Add New" clears the form
        clearForm();
        return;
    }

    showStatus(sectionStatus, 'Adding section...', false);
    try {
        const result = await fetchData('add_or_update_section', 'POST', { sectionName: sectionName });
        showStatus(sectionStatus, result.message, false);
        clearForm();
        await loadResources();
    } catch (error) {
        showStatus(sectionStatus, `Failed to add section: ${error.message}`, true);
    }
});

updateSectionBtn.addEventListener('click', async () => {
    if (selectedSectionIndexForEdit === null) {
        showStatus(sectionStatus, 'No section selected to update.', true);
        return;
    }
    const sectionName = sectionNameInput.value.trim();
    if (!sectionName) {
        showStatus(sectionStatus, 'Section name cannot be empty.', true);
        return;
    }

    showStatus(sectionStatus, 'Updating section...', false);
    try {
        const result = await fetchData('add_or_update_section', 'POST', {
            sectionName: sectionName,
            sectionIndex: selectedSectionIndexForEdit
        });
        showStatus(sectionStatus, result.message, false);
        clearForm();
        await loadResources();
    } catch (error) {
        showStatus(sectionStatus, `Failed to update section: ${error.message}`, true);
    }
});

deleteSectionBtn.addEventListener('click', async () => {
    if (selectedSectionIndexForEdit === null) {
        showStatus(sectionStatus, 'No section selected to delete.', true);
        return;
    }
    const sectionData = currentResourcesData.buttons[selectedSectionIndexForEdit];
    if (sectionData && sectionData.buttons.some(b => b.name !== "Empty")) {
        showStatus(sectionStatus, 'Cannot delete section: it contains active resources. Please move or delete resources from this section first.', true);
        return;
    }
    if (!confirm(`Are you sure you want to delete the section "${sectionData.section}"? This will remove the section and any empty button slots within it.`)) {
        return;
    }

    showStatus(sectionStatus, 'Deleting section...', false);
    try {
        const result = await fetchData('delete_section', 'POST', { sectionIndex: selectedSectionIndexForEdit });
        showStatus(sectionStatus, result.message, false);
        clearForm();
        await loadResources();
    } catch (error) {
        showStatus(sectionStatus, `Failed to delete section: ${error.message}`, true);
    }
});


unityPackageFileInput.addEventListener('change', () => {
    const file = unityPackageFileInput.files[0];
    if (file) {
        resourceNameInput.value = file.name.replace('.unitypackage', '');
    } else {
        resourceNameInput.value = '';
    }
});

uploadFileBtn.addEventListener('click', async () => {
    const file = unityPackageFileInput.files[0];
    if (!file) {
        showStatus(uploadStatus, 'Please select a file to upload.', true);
        return;
    }
    showStatus(uploadStatus, 'Uploading file...', false);
    const formData = new FormData();
    formData.append('unityPackage', file);
    try {
        const result = await fetchData('upload_file', 'POST', formData);
        showStatus(uploadStatus, result.message, false);
        resourceURLInput.value = result.url;
        resourceNameInput.value = result.filename.replace('.unitypackage', '');
    } catch (error) {
        showStatus(uploadStatus, `Upload failed: ${error.message}`, true);
    }
});

addOrUpdateResourceBtn.addEventListener('click', async () => {
    const name = resourceNameInput.value.trim();
    const url = resourceURLInput.value.trim();
    const accessLevel = parseInt(accessLevelInput.value, 10);
    const label = buttonLabelInput.value.trim();
    const sectionIndex = parseInt(sectionSelect.value, 10);

    if (!name || !url || isNaN(accessLevel) || !label || isNaN(sectionIndex)) {
        showStatus(resourceStatus, 'All fields (Resource Name, URL, Access Level, Button Label, Section) are required.', true);
        return;
    }
    showStatus(resourceStatus, 'Saving resource...', false);
    const data = {
        name: name,
        url: url,
        access_level: accessLevel,
        label: label,
        sectionIndex: sectionIndex,
        buttonIndex: selectedResourceButtonIndex // Pass null for new, or existing index
    };
    try {
        const result = await fetchData('add_or_update_resource', 'POST', data);
        showStatus(resourceStatus, result.message, false);
        clearForm();
        await loadResources();
    } catch (error) {
        showStatus(resourceStatus, `Failed to save resource: ${error.message}`, true);
    }
});

deleteResourceBtn.addEventListener('click', async () => {
    if (!selectedResourceName) {
        showStatus(resourceStatus, 'No resource selected for deletion.', true);
        return;
    }
    if (!confirm(`Are you sure you want to delete the resource "${selectedResourceName}"? This will also remove the associated file from the server and empty its button slot.`)) {
        return;
    }
    showStatus(resourceStatus, 'Deleting resource...', false);
    try {
        const result = await fetchData('delete_resource', 'POST', { name: selectedResourceName });
        showStatus(resourceStatus, result.message, false);
        clearForm();
        await loadResources();
    } catch (error) {
        showStatus(resourceStatus, `Failed to delete resource: ${error.message}`, true);
    }
});

// --- Initial Page Load Setup ---
document.addEventListener('DOMContentLoaded', () => {
    handleLogout(); // Start in a logged-out state, hiding admin sections.
});