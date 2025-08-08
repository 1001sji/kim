const { app, BrowserWindow, ipcMain, screen, shell } = require('electron');
const path = require('path');
const fs = require('fs');
const https = require('https'); // For downloading files. Use 'http' if your server uses it.


// Keep a global reference of the window object, if you don't, the window will
// be closed automatically when the JavaScript object is garbage collected.
let mainWindow;

async function createWindow() {
  // Dynamically import the ES module 'wallpaper'
  const { setWallpaper, getScreens } = await import('wallpaper');

  // Create the browser window.
  mainWindow = new BrowserWindow({
    width: 1200,
    height: 800,
    webPreferences: {
      // Preload script to safely expose Node.js APIs to the renderer process
      preload: path.join(__dirname, 'preload.js'),
      // It's recommended to run renderer processes in a sandboxed environment
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  // and load the index.html of the app.
  mainWindow.loadFile('index.html');

  // Open the DevTools for debugging.
  mainWindow.webContents.openDevTools();

  // Emitted when the window is closed.
  mainWindow.on('closed', function () {
    // Dereference the window object, usually you would store windows
    // in an array if your app supports multi windows, this is the time
    // when you should delete the corresponding element.
    mainWindow = null;
  });

  // --- IPC Handlers ---

  // Handle request to get display information
  ipcMain.handle('get-displays', () => {
    return screen.getAllDisplays();
  });

  // Handle request to set wallpaper
  ipcMain.handle('set-wallpaper', async (event, { imagePath, displayId }) => {
    try {
      // The 'wallpaper' package now uses screen IDs which are strings on some platforms.
      // The main screen is often 'main'. We need to be careful about what ID we pass.
      // For simplicity, we'll rely on the IDs from `getAllDisplays`.
      await setWallpaper(imagePath, { screen: displayId === 'all' ? 'all' : displayId });
      return { success: true };
    } catch (error) {
      console.error('Failed to set wallpaper:', error);
      return { success: false, message: error.message };
    }
  });

  // Handle request to open a URL in the default browser
  ipcMain.handle('open-in-browser', (event, url) => {
    shell.openExternal(url);
  });

  // Handle request to download a file to a temporary directory
  ipcMain.handle('download-file-to-temp', (event, { url, fileName }) => {
    return new Promise((resolve, reject) => {
      const tempPath = path.join(app.getPath('temp'), fileName);
      const fileStream = fs.createWriteStream(tempPath);

      https.get(url, (response) => {
        if (response.statusCode !== 200) {
          return reject(new Error(`Download failed: Server responded with ${response.statusCode}`));
        }
        // Handle potential redirects
        if (response.statusCode > 300 && response.statusCode < 400 && response.headers.location) {
          // For simplicity, we are not handling redirects recursively.
          // A more robust solution would use a library like 'got' or 'axios'.
          return https.get(response.headers.location, (redirectResponse) => {
            redirectResponse.pipe(fileStream);
          });
        }

        response.pipe(fileStream);

        fileStream.on('finish', () => {
          fileStream.close();
          resolve({ success: true, path: tempPath });
        });

      }).on('error', (err) => {
        fs.unlink(tempPath, () => {}); // Clean up failed download
        reject(new Error(`Download error: ${err.message}`));
      });
    });
  });
}

// This method will be called when Electron has finished
// initialization and is ready to create browser windows.
// Some APIs can only be used after this event occurs.
app.whenReady().then(createWindow);

// Quit when all windows are closed, except on macOS. There, it's common
// for applications and their menu bar to stay active until the user quits
// explicitly with Cmd + Q.
app.on('window-all-closed', function () {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

app.on('activate', function () {
  // On macOS it's common to re-create a window in the app when the
  // dock icon is clicked and there are no other windows open.
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});
