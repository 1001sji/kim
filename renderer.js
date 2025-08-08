// renderer.js

// --- Configuration ---
// IMPORTANT: The user must configure this to their GnuBoard installation URL.
// For example: 'http://your-domain.com/gnuboard'
const API_BASE_URL = 'http://localhost'; // <--- CONFIGURE THIS

// --- DOM Elements ---
const galleryGrid = document.getElementById('gallery-grid');
const categoryNavs = document.querySelectorAll('.sidebar nav li');

// --- State ---
let allPosts = []; // Store all fetched posts
let currentPage = 1;
let currentBoard = 'wallpaper_free'; // Default board
let isLoading = false;

// --- DOM Elements (Modal) ---
const detailModal = document.getElementById('detail-modal');
const modalCloseBtn = document.getElementById('modal-close-btn');
const detailTitle = document.getElementById('detail-title');
const detailImage = document.getElementById('detail-image');
const detailInfoList = document.getElementById('detail-info-list');
const monitorSelection = document.getElementById('monitor-selection');
const applyBtn = document.getElementById('detail-apply-btn');
const downloadBtn = document.getElementById('detail-download-btn');


/**
 * Opens the detail view modal with information about a specific post.
 * @param {string} postId - The ID of the post to display.
 */
async function openDetailView(postId) {
    const post = allPosts.find(p => p.id === postId);
    if (!post) {
        console.error('Post not found!');
        return;
    }

    // A helper function to provide user feedback in the modal
    const showMessage = (message, isError = false) => {
        const messageEl = document.createElement('p');
        messageEl.textContent = message;
        messageEl.style.color = isError ? '#ff6b6b' : '#2ecc71';
        messageEl.style.textAlign = 'center';
        applyBtn.parentElement.prepend(messageEl);
        setTimeout(() => messageEl.remove(), 3000);
    };

    // Populate basic info
    detailTitle.textContent = post.title;
    detailInfoList.innerHTML = `
        <li><strong>Author:</strong> ${post.author}</li>
        <li><strong>Date:</strong> ${new Date(post.date).toLocaleDateString()}</li>
        <li><strong>Views:</strong> ${post.views}</li>
        <li><strong>Files:</strong> ${post.files.length}</li>
    `;

    // Find the best quality image to display (largest file, typically)
    // For now, we'll just find the first image file.
    const imageFile = post.files.find(f => f.source.match(/\.(jpg|jpeg|png|gif)$/i));
    let previewUrl = imageFile
        ? (imageFile.view_url.startsWith('http') ? imageFile.view_url : `${API_BASE_URL}${imageFile.view_url}`)
        : (post.thumbnail.startsWith('http') ? post.thumbnail : `${API_BASE_URL}/${post.thumbnail}`);

    // The view_url from get_file_list needs to be constructed properly on the PHP side.
    // Let's assume it points to a downloadable link. We'll use the thumbnail for now.
    previewUrl = post.thumbnail.startsWith('http') ? post.thumbnail : `${API_BASE_URL}/${post.thumbnail.substring(1)}`;
    detailImage.src = previewUrl;

    // Populate monitor selection
    monitorSelection.innerHTML = 'Loading monitors...';
    try {
        const displays = await window.electronAPI.getDisplays();
        let monitorHtml = '';
        displays.forEach((display, index) => {
            monitorHtml += `
                <label>
                    <input type="radio" name="monitor" value="${display.id}" ${index === 0 ? 'checked' : ''}>
                    Monitor ${index + 1} (${display.size.width}x${display.size.height}) ${display.primary ? '(Primary)' : ''}
                </label>
            `;
        });
        monitorHtml += `
            <label>
                <input type="radio" name="monitor" value="all">
                All Monitors
            </label>
        `;
        monitorSelection.innerHTML = monitorHtml;
    } catch (error) {
        monitorSelection.innerHTML = '<p style="color: #ff6b6b;">Could not load display info.</p>';
        console.error('Failed to get display info:', error);
    }

    // Add event listeners for buttons
    applyBtn.onclick = async () => {
        const selectedMonitor = document.querySelector('input[name="monitor"]:checked');
        if (!selectedMonitor) {
            showMessage('Please select a monitor.', true);
            return;
        }

        const fileToDownload = post.files.find(f => f.source.match(/\.(jpg|jpeg|png|gif|mp4)$/i));
        if (!fileToDownload) {
            showMessage('No downloadable image file found for this post.', true);
            return;
        }

        applyBtn.textContent = 'Downloading...';
        applyBtn.disabled = true;

        try {
            // Construct the full download URL
            const downloadUrl = `${API_BASE_URL}/bbs/download.php?bo_table=${post.bo_table}&wr_id=${post.id}&no=0`;

            const downloadResult = await window.electronAPI.downloadFileToTemp({
                url: downloadUrl,
                fileName: fileToDownload.source,
            });

            if (!downloadResult.success) {
                throw new Error(downloadResult.message || 'Download failed.');
            }

            applyBtn.textContent = 'Applying...';
            const setResult = await window.electronAPI.setWallpaper({
                imagePath: downloadResult.path,
                displayId: selectedMonitor.value,
            });

            if (setResult.success) {
                showMessage('Wallpaper applied successfully!');
            } else {
                throw new Error(setResult.message || 'Failed to set wallpaper.');
            }

        } catch (error) {
            console.error('Failed to apply wallpaper:', error);
            showMessage(error.message, true);
        } finally {
            applyBtn.textContent = 'Apply to Selected';
            applyBtn.disabled = false;
        }
    };

    downloadBtn.onclick = () => {
        const fileToDownload = post.files[0]; // Download the first attached file
        if (!fileToDownload) {
            showMessage('No downloadable file found.', true);
            return;
        }
        // We need to find the index of the file to pass to download.php
        const fileIndex = post.files.findIndex(f => f.source === fileToDownload.source);
        const downloadUrl = `${API_BASE_URL}/api/file_download.php?bo_table=${post.bo_table}&wr_id=${post.id}&no=${fileIndex}`;
        window.electronAPI.openInBrowser(downloadUrl);
    };

    // Show the modal
    detailModal.style.display = 'flex';
}

/**
 * Renders a list of wallpaper posts into the gallery grid.
 * @param {Array<Object>} posts - An array of post objects from the API.
 * @param {boolean} append - If true, appends posts to the grid instead of clearing it.
 */
function renderWallpapers(posts, append = false) {
    if (!append) {
        galleryGrid.innerHTML = '';
        allPosts = []; // Reset the stored posts
    }

    if (!posts || posts.length === 0) {
        if (!append) {
            galleryGrid.innerHTML = '<p>No wallpapers found in this category.</p>';
        }
        return;
    }

    // Add the board table to each post object for later use
    const processedPosts = posts.map(p => ({ ...p, bo_table: currentBoard }));
    allPosts.push(...processedPosts); // Add new posts to our local store

    processedPosts.forEach(post => {
        const item = document.createElement('div');
        item.className = 'gallery-item';

        // Construct absolute URL for the thumbnail if it's relative
        const thumbnailUrl = post.thumbnail && !post.thumbnail.startsWith('http')
            ? `${API_BASE_URL}/${post.thumbnail.startsWith('/') ? post.thumbnail.substring(1) : post.thumbnail}`
            : (post.thumbnail || ''); // Use empty string if thumbnail is null

        item.innerHTML = `
            <img src="${thumbnailUrl}" alt="${post.title}" loading="lazy" onerror="this.style.display='none'; this.parentElement.querySelector('.no-image').style.display='flex';">
            <div class="no-image" style="display: none;"><span>No Preview</span></div>
            <div class="item-overlay">
                <p class="item-title">${post.title}</p>
            </div>
        `;

        item.addEventListener('click', () => openDetailView(post.id));

        galleryGrid.appendChild(item);
    });
}

/**
 * Fetches wallpapers from the backend API.
 * @param {string} bo_table - The board table name (e.g., 'wallpaper_free').
 * @param {number} page - The page number to fetch.
 */
async function loadWallpapers(bo_table, page = 1, append = false) {
    if (isLoading) return;
    isLoading = true;
    if (!append) {
        galleryGrid.innerHTML = '<p>Loading wallpapers...</p>';
    }

    try {
        const response = await fetch(`${API_BASE_URL}/api/board_list.php?bo_table=${bo_table}&page=${page}&limit=30`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}, check API_BASE_URL`);
        }
        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        renderWallpapers(data.posts, append);
        currentPage = page;

    } catch (error) {
        console.error('Failed to load wallpapers:', error);
        galleryGrid.innerHTML = `<p style="color: #ff6b6b;">Error loading wallpapers. Please check the API URL and ensure the backend server is running.<br><br>Details: ${error.message}</p>`;
    } finally {
        isLoading = false;
    }
}

// --- Event Listeners ---

// Modal close events
modalCloseBtn.addEventListener('click', () => {
    detailModal.style.display = 'none';
});

detailModal.addEventListener('click', (event) => {
    // Close modal if the overlay (background) is clicked
    if (event.target === detailModal) {
        detailModal.style.display = 'none';
    }
});


// Initial load
document.addEventListener('DOMContentLoaded', () => {
    // The board IDs should match the Gnuboard setup
    const boardMapping = {
        '홈': 'wallpaper_free',
        '프리미엄': 'wallpaper_premium',
        '동영상': 'wallpaper_video'
    };

    categoryNavs.forEach(nav => {
        nav.addEventListener('click', () => {
            const categoryName = nav.innerText.substring(2).trim(); // "🏠 홈" -> "홈"
            const boardId = boardMapping[categoryName];
            if (boardId && boardId !== currentBoard) {
                currentBoard = boardId;
                loadWallpapers(currentBoard, 1, false);
            }
        });
    });

    // Load default category
    loadWallpapers(currentBoard);
});

// Infinite scroll
galleryGrid.addEventListener('scroll', () => {
    // If user has scrolled to the bottom of the grid
    if (galleryGrid.scrollTop + galleryGrid.clientHeight >= galleryGrid.scrollHeight - 100) {
        if (!isLoading) {
            loadWallpapers(currentBoard, currentPage + 1, true);
        }
    }
});
