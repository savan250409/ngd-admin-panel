/*
 * Generates a video thumbnail in the browser using only <video> + <canvas>.
 * No libraries, no server-side ffmpeg.
 *
 *   captureVideoFrame(srcOrFile, { at: 0.25, maxWidth: 720, type: 'image/jpeg', quality: 0.85 })
 *     -> Promise<string dataURL>
 * `at` < 1 is a fraction of the duration, otherwise seconds.
 */
(function () {
    'use strict';

    window.captureVideoFrame = function (src, opts) {
        opts = Object.assign({ at: 0.25, maxWidth: 720, type: 'image/webp', quality: 0.85, timeout: 20000 }, opts || {});

        return new Promise(function (resolve, reject) {
            const video = document.createElement('video');
            const objectUrl = src instanceof Blob ? URL.createObjectURL(src) : null;
            let done = false;

            const finish = function (err, data) {
                if (done) return;
                done = true;
                clearTimeout(timer);
                if (objectUrl) URL.revokeObjectURL(objectUrl);
                video.removeAttribute('src');
                video.load();
                err ? reject(err) : resolve(data);
            };
            const timer = setTimeout(function () { finish(new Error('Timed out reading the video')); }, opts.timeout);

            video.muted = true;
            video.playsInline = true;
            video.preload = 'auto';
            video.crossOrigin = 'anonymous';

            video.addEventListener('error', function () { finish(new Error('This video format cannot be read by the browser')); });

            video.addEventListener('loadedmetadata', function () {
                const d = isFinite(video.duration) ? video.duration : 1;
                let t = opts.at < 1 ? d * opts.at : opts.at;
                t = Math.max(0.05, Math.min(t, Math.max(d - 0.05, 0.05)));
                video.currentTime = t;
            });

            // True when every sampled pixel is (near) black or transparent, i.e. the decoder
            // had not painted the frame yet when "seeked" fired.
            const isBlank = function (ctx, w, h) {
                for (let gx = 1; gx < 6; gx++) {
                    for (let gy = 1; gy < 6; gy++) {
                        const d = ctx.getImageData(Math.floor(w * gx / 6), Math.floor(h * gy / 6), 1, 1).data;
                        if (d[3] > 0 && d[0] + d[1] + d[2] > 24) return false;
                    }
                }
                return true;
            };

            let attempts = 0;
            const draw = function () {
                try {
                    const w = video.videoWidth, h = video.videoHeight;
                    if (!w || !h) return finish(new Error('Video has no picture'));
                    const scale = Math.min(1, opts.maxWidth / w);
                    const canvas = document.createElement('canvas');
                    canvas.width = Math.round(w * scale);
                    canvas.height = Math.round(h * scale);
                    const ctx = canvas.getContext('2d', { willReadFrequently: true });
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    if (isBlank(ctx, canvas.width, canvas.height) && attempts++ < 6) {
                        // Give the decoder a moment; after a few tries nudge to a nearby frame.
                        if (attempts === 4 && isFinite(video.duration)) {
                            video.currentTime = Math.min(video.duration - 0.05, video.currentTime + 0.2);
                            return;
                        }
                        return setTimeout(draw, 150);
                    }
                    finish(null, canvas.toDataURL(opts.type, opts.quality));
                } catch (e) {
                    finish(e);
                }
            };
            video.addEventListener('seeked', function () { setTimeout(draw, 60); });

            video.src = objectUrl || src;
        });
    };
})();
