// --- State ---

let data = { keys: {}, groups: {} }; // Local cache of DB
let currentKeyGroups = []; // Groups currently being added in the 'New Key' form
let currentAllowedGroups = []; // Groups allowed for the new post
let currentAlbums = []; // Albums currently being added in the 'New Album' form

// --- Utility Functions ---

const getEl = (id) => document.getElementById(id);

const fetchApi = async (params) => {
    // Handle POST (FormData)
    const formData = new FormData();
    Object.keys(params).forEach(key => {
        const value = params[key];
        if (Array.isArray(value)) {
            // Handle arrays (like tags)
            value.forEach(v => formData.append(`${key}[]`, v));
        } else {
            formData.append(key, value);
        }
    });

    return (await fetch('admin.php', {
        method: 'POST',
        body: formData
    })).json();
};

const loadData = async () => {
    const res = await fetchApi({
        action: 'load_data'
    });
    if (res.success) {
        data = res.data;
        updateUI();
    }
};

const loadAlbums = async () => {
    const res = await fetchApi({
        action: 'load_albums'
    });
    if (res.success) {
        currentAlbums = res.data;
        updateAlbumsList();
    }
};

// --- API Functions ---

const createGroup = async () => {
    const res = await fetchApi({
        action: 'create_group',
        group_name: getEl('new_group_name').value
    });
    if (res.success) {
        data = res.data;
        updateUI();
        getEl('new_group_name').value = '';
    } else {
        alert("Error: " + res.message);
    }
};

const deleteGroup = async (group) => {
    if (!confirm(`Are you sure you want to delete the group "${group}"? This action cannot be undone.`)) return;

    const res = await fetchApi({
        action: 'delete_group',
        group_name: group
    });
    if (res.success) {
        data = res.data;
        updateUI();
    } else {
        alert("Error: " + res.message);
    }
};

const generateKey = async () => {
    const res = await fetchApi({
        action: 'generate_key_string'
    });
    if (res.success) getEl('new_key_display').value = res.data.key;
};

const saveKey = async () => {
    const key = getEl('new_key_display').value;
    if (!key) return alert("Please generate a key first.");
    if (currentKeyGroups.length === 0) return alert("Please add at least one group.");

    const res = await fetchApi({
        action: 'save_key',
        key: key,
        groups: currentKeyGroups
    });

    if (res.success) {
        // Reset form
        getEl('new_key_display').value = '';
        currentKeyGroups = [];
        renderKeyGroups();
        loadData(); // Refresh list
    } else {
        alert("Error: " + res.message);
    }
};

const deleteKey = async (key) => {
    if (!confirm('Are you sure you want to delete this key?')) return;

    const res = await fetchApi({
        action: 'delete_key',
        key: key
    });

    if (res.success) {
        loadData();
    } else {
        alert("Error: " + res.message);
    }
};

const createAlbum = async () => {
    const res = await fetchApi({
        action: 'create_album',
        title: getEl('title').value,
        subtitle: getEl('subtitle').value,
        slug: getEl('slug').value,
        date: getEl('date').value,
        img_dir: getEl('img_dir').value,
        theme: getEl('theme').value,
        allowed_groups: currentAllowedGroups,
        process_images: getEl('process_images').value,
        extract_exif: getEl('extract_exif').value,
        max_image_wh: getEl('max_image_wh').value,
        max_preview_wh: getEl('max_preview_wh').value,
        conversion_quality: getEl('conversion_quality').value,
    });

    if (res.success) {
        alert("Album created successfully.");
        // Reset form
        getEl('albumForm').reset();
        // Reset allowed groups
        currentAllowedGroups = [];
        renderAllowedGroups();
        // Update album list
        loadAlbums();
    } else {
        alert("Error: " + res.message);
    }
};

const updateAlbum = async () => {
    const images = Array.from(document.querySelectorAll('[name="images[]"]')).map(el => el.value);

    const res = await fetchApi({
        action: 'update_album',
        album: getEl('updateAlbumSlug').value,
        title: getEl('title').value,
        subtitle: getEl('subtitle').value,
        date: getEl('date').value,
        theme: getEl('theme').value,
        images: images,
        allowed_groups: currentAllowedGroups,
    });

    if (res.success) {
        alert("Album updated successfully.");
    } else {
        alert("Error: " + res.message);
    }
};

const deleteAlbum = async (albumSlug) => {
    if (!confirm(`Are you sure you want to delete the album "${albumSlug}"? This action cannot be undone.`)) return;
    const res = await fetchApi({
        action: 'delete_album',
        album: albumSlug,
    });

    if (res.success) {
        alert("Album deleted successfully.");
        // Redirect to albums
        window.location.href = 'admin.php';
    } else {
        alert("Error: " + res.message);
    }
};

// --- UI Functions ---

const copyKey = (key) => {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(refractaURL + '?key=' + key);
        alert("Access link with key copied to clipboard.");
    } else {
        // Fallback for browsers that do not support navigator.clipboard
        // or if the page is not served over HTTPS.
        prompt("Copy the access link with key:", refractaURL + '?key=' + key);
    }
};


const updateUI = () => {
    if (adminPanel) {
        renderKeysList();
        renderGroupsList();
        updateSelect('key_group_selector', data.groups, 'Select Group');
    }
    updateSelect('group_selector', data.groups, 'Select Group');
};
const updateAlbumsList = () => {
    if (adminPanel) {
        renderAlbumsList();
    }
};

const renderAlbumsList = () => {
    const container = getEl('albums_list');
    // Clear
    container.innerHTML = '';

    if (Object.keys(currentAlbums).length === 0) {
        container.innerHTML = '<div class="no-items">No albums found. Create one above.</div>';
        return;
    }

    currentAlbums.forEach((album) => {
        const div = document.createElement('div');
        div.className = 'list-item';

        let tagsHtml = album.data.access.map(t => `<span class="tag">${t}</span>`).join('');
        if (album.data.access.length === 0) tagsHtml = '<span class="tag" style="color: var(--text); font-weight: bold;">Public</span>';
        div.innerHTML = `
    <div>
        <div class="item-title"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" d="M432 112V96a48.14 48.14 0 0 0-48-48H64a48.14 48.14 0 0 0-48 48v256a48.14 48.14 0 0 0 48 48h16"/><rect width="400" height="336" x="96" y="128" fill="none" stroke="currentColor" stroke-linejoin="round" stroke-width="32" rx="45.99" ry="45.99"/><ellipse cx="372.92" cy="219.64" fill="none" stroke="currentColor" stroke-miterlimit="10" stroke-width="32" rx="30.77" ry="30.55"/><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="32" d="M342.15 372.17L255 285.78a30.93 30.93 0 0 0-42.18-1.21L96 387.64M265.23 464l118.59-117.73a31 31 0 0 1 41.46-1.87L496 402.91"/></svg> ${album.data.title}</div>
        <div class="item-count">${album.data.count}</div>
        <div class="item-desc">${album.data.datestr} :: ${album.dir}</div>
        <div class="tags-list">${tagsHtml}</div>
    </div>
    <a href="${refractaURL}/${album.dir}" target="_blank"><button class="btn-alter">View</button></a>
    <a href="${refractaURL}/admin.php?album=${album.dir}"><button class="btn-primary">Edit</button></a>
`;
        container.appendChild(div);
    });
};

const renderKeysList = () => {
    const container = getEl('keys_list');
    // Clear
    container.innerHTML = '';

    if (Object.keys(data.keys).length === 0) {
        container.innerHTML = '<div class="no-items">No keys found. Create one above.</div>';
        return;
    }

    Object.entries(data.keys).forEach(([key, tags]) => {
        const div = document.createElement('div');
        div.className = 'list-item';
        const idAdminKey = (tags.includes('admin') && tags.length === 1);
        const tagsHtml = tags.map(t => `<span class="tag">${t}</span>`).join('');

        div.innerHTML = `
    <div>
        <div class="item-title"><svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512"><path fill="currentColor" fill-rule="evenodd" d="M426.667 320v-64H384v-85.333c0-70.305-57.51-128-128-128s-128 57.695-128 128V256H85.333v213.334h128v-42.667H128v-128h256v32.749c12.551-7.26 27.124-11.416 42.667-11.416m-256-149.333c0-46.338 38.002-85.333 85.333-85.333s85.333 38.995 85.333 85.333V256H170.667zm320 234.667c0-35.347-28.654-64-64-64c-27.866 0-51.573 17.809-60.359 42.666H234.667v42.667H256v42.667h64v-42.667h46.308c8.786 24.857 32.493 42.667 60.359 42.667c35.346 0 64-28.654 64-64m-96 0c0-17.673 14.327-32 32-32s32 14.327 32 32s-14.327 32-32 32s-32-14.327-32-32" clip-rule="evenodd"/></svg> ${key.slice(0, 6)}...${key.slice(-6)}</div>
        <div class="tags-list">${tagsHtml}</div>
    </div>
    <button class="btn-primary" onclick="copyKey('${key}')">Copy</button>
    <button class="btn-danger" onclick="deleteKey('${key}')" ${idAdminKey ? 'style="opacity: 0.2; pointer-events: none;"' : ''}>Delete</button>
`;
        container.appendChild(div);
    });
};

const renderGroupsList = () => {
    const container = getEl('groups_list');
    // Clear
    container.innerHTML = '';

    if (Object.keys(data.groups).length === 0) {
        container.innerHTML = '<div class="no-items">No groups found. Create one above.</div>';
        return;
    }

    Object.entries(data.groups).forEach(([groupid, group]) => {
        const div = document.createElement('div');
        div.className = 'list-item';
        if (group === 'admin') {
            div.innerHTML = `
            <div>
                <div class="item-title"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="m2 8l1.304 1.043a4 4 0 0 0 5.995-1.181L12 3l2.701 4.862a4 4 0 0 0 5.996 1.18L22 8l-1.754 8.77a2.56 2.56 0 0 1-1.367 1.79v0a15.38 15.38 0 0 1-13.758 0v0a2.56 2.56 0 0 1-1.367-1.79z" /><path d="M8 15c2.596 1.333 5.404 1.333 8 0" /></g></svg> ${group}</div>
            </div>
            <button class="btn-danger" onclick="deleteGroup('${groupid}')" style="opacity: 0.2; pointer-events: none;">Delete</button>`;

        } else {
            div.innerHTML = `
            <div>
                <div class="item-title"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M16 3.128a4 4 0 0 1 0 7.744M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/></g></svg> ${group}</div>
            </div>
            <button class="btn-danger" onclick="deleteGroup('${groupid}')">Delete</button>`;
        }
        container.appendChild(div);
    });
};

const updateSelect = (selectId, options, defaultText = "Select Option") => {
    const select = getEl(selectId);
    select.innerHTML = `<option value="" disabled selected>${defaultText}</option>`;

    if (options && options.length) {
        options.forEach(opt => {
            if (opt === 'admin') return;
            const el = document.createElement('option');
            el.value = opt;
            el.text = opt;
            select.appendChild(el);
        });
    }
};

const renderTagList = ({ containerId, inputContainerId, items, removeGlobalParams, emptyText = "No items selected" }) => {
    const container = getEl(containerId);
    const inputsContainer = inputContainerId ? getEl(inputContainerId) : null;
    container.innerHTML = '';
    if (inputsContainer) inputsContainer.innerHTML = '';
    if (items.length === 0) {
        container.innerHTML = `<span class="no-items">${emptyText}</span>`;
        return;
    }
    items.forEach(item => {
        const tag = document.createElement('div');
        tag.className = 'tag';
        tag.style.display = 'flex';
        tag.style.alignItems = 'center';
        tag.style.gap = '0.5rem';

        const removeCall = `${removeGlobalParams.fn}('${item}')`;

        tag.innerHTML = `${item} <span class="tag-delete" onclick="${removeCall}">&times;</span>`;
        container.appendChild(tag);

        if (inputsContainer && removeGlobalParams.inputName) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = removeGlobalParams.inputName;
            input.value = item;
            inputsContainer.appendChild(input);
        }
    });
};

const renderAllowedGroups = () => {
    renderTagList({
        containerId: 'allowed_groups_display',
        inputContainerId: 'allowed_groups_inputs',
        items: currentAllowedGroups,
        removeGlobalParams: { fn: 'removeAllowedGroup', inputName: 'allowed_groups[]' },
        emptyText: 'Open for everyone'
    });
};

const renderKeyGroups = () => {
    renderTagList({
        containerId: 'key_groups_display',
        inputContainerId: null,
        items: currentKeyGroups,
        removeGlobalParams: { fn: 'removeKeyGroup' },
        emptyText: 'No groups assigned'
    });
};

addImageElement = (event) => {
    // Add input element after the parent of clicked element
    const parent = event.target.parentElement;
    const select = document.createElement('select');
    select.name = 'images[]';
    // Add options to select
    const option = document.createElement('option');
    option.value = 'title';
    option.text = 'Title';
    select.appendChild(option);
    // Text
    const option2 = document.createElement('option');
    option2.value = 'text';
    option2.text = 'Text';
    select.appendChild(option2);

    const input = document.createElement('input');
    const div = document.createElement('div');
    div.className = 'admin-input-item-wrapper';
    input.type = 'text';
    input.name = 'images[]';
    div.appendChild(select);
    div.appendChild(input);
    parent.insertAdjacentElement('afterend', div);
    input.click();
}

deleteImageElement = (event) => {
    // Confirm delete
    if (!confirm('Are you sure you want to delete this element?')) return;
    // Delete element
    const parent = event.target.parentElement;
    parent.remove();
}

moveImageElement = (event, direction) => {
    // Swap element with next or previous
    const node = event.target.parentElement;
    const parent = node.parentElement;
    const childNode = parent.childNodes;
    const index = Array.from(childNode).indexOf(node);
    let secondIndex;
    if (direction === 'next') {
        secondIndex = index + 1;
    } else {
        secondIndex = index - 1;
    }
    if (index && childNode[secondIndex] && (childNode[secondIndex].classList.contains('admin-input-item-wrapper') || childNode[secondIndex].classList.contains('admin-text-item-wrapper') || childNode[secondIndex].classList.contains('admin-image-item-wrapper'))) {
        if (direction === 'next') {
            parent.insertBefore(childNode[secondIndex], childNode[index]);
        } else {
            parent.insertBefore(childNode[index], childNode[secondIndex]);
        }
    }
}

// --- Group List Functions ---

const addAllowedGroup = () => {
    handleAddGroupClick('group_selector', currentAllowedGroups, renderAllowedGroups)
}

const removeAllowedGroup = (group) => {
    currentAllowedGroups = currentAllowedGroups.filter(g => g !== group);
    renderAllowedGroups();
};

const addKeyGroup = () => {
    handleAddGroupClick('key_group_selector', currentKeyGroups, renderKeyGroups)
}

const removeKeyGroup = (group) => {
    currentKeyGroups = currentKeyGroups.filter(g => g !== group);
    renderKeyGroups();
};

const handleAddGroupClick = (selectId, list, renderFn) => {
    const select = getEl(selectId);
    const val = select.value;
    if (val && !list.includes(val)) {
        list.push(val);
        renderFn();
    }
    select.value = "";
};
const toggleElementDisplay = (elementId) => {
    const element = getEl(elementId);
    element.style.display = element.style.display === 'none' ? 'block' : 'none';
};

// Load Data on Init
loadData();
loadAlbums();
// If not admin panel and exists allowed groups, set them
if (!adminPanel && albumAllowedGroups && albumAllowedGroups.length > 0) {
    currentAllowedGroups = albumAllowedGroups;
    renderAllowedGroups();
}
