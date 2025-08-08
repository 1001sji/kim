const { contextBridge, ipcRenderer } = require('electron');

// Expose protected methods that allow the renderer process to use
// the ipcRenderer without exposing the entire object
contextBridge.exposeInMainWorld('electronAPI', {
  /**
   * Invokes the 'get-displays' channel in the main process.
   * @returns {Promise<Electron.Display[]>} A promise that resolves with an array of display objects.
   */
  getDisplays: () => ipcRenderer.invoke('get-displays'),

  /**
   * Invokes the 'set-wallpaper' channel in the main process.
   * @param {object} options - The options for setting the wallpaper.
   * @param {string} options.imagePath - The path to the image file.
   * @param {string} options.displayId - The ID of the display to set the wallpaper on ('all' for all displays).
   * @returns {Promise<{success: boolean, message?: string}>} A promise that resolves with the result of the operation.
   */
  setWallpaper: (options) => ipcRenderer.invoke('set-wallpaper', options),

  /**
   * Invokes the 'open-in-browser' channel in the main process.
   * @param {string} url - The URL to open.
   */
  openInBrowser: (url) => ipcRenderer.invoke('open-in-browser', url),

  /**
   * Invokes the 'download-file-to-temp' channel in the main process.
   * @param {object} options - The options for downloading the file.
   * @param {string} options.url - The URL of the file to download.
   * @param {string} options.fileName - The name to save the file as in the temp directory.
   * @returns {Promise<{success: boolean, path?: string, message?: string}>} A promise that resolves with the result.
   */
  downloadFileToTemp: (options) => ipcRenderer.invoke('download-file-to-temp', options),
});
