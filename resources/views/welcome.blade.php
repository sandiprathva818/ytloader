<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>YT Downloader - Professional Video & Audio Downloader</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        heading: ['Outfit', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            200: '#fecdd3',
                            300: '#fda4af',
                            400: '#fb7185',
                            500: '#f43f5e',
                            600: '#e11d48',
                            700: '#be123c',
                            800: '#9f1239',
                            900: '#881337',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gradient-text {
            background: linear-gradient(135deg, #fb7185 0%, #e11d48 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        body {
            background-color: #0d0d0d;
            color: #ffffff;
        }
    </style>
</head>
<body class="antialiased min-h-screen">
    <div x-data="downloader()" class="relative overflow-x-hidden">
        <!-- Background Elements -->
        <div class="absolute top-0 left-0 w-full h-full -z-10 pointer-events-none">
            <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-primary-600/20 rounded-full blur-[120px]"></div>
            <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-purple-600/20 rounded-full blur-[120px]"></div>
        </div>

        <!-- Navigation -->
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex justify-between items-center">
            <div class="flex items-center space-x-2">
                <div class="w-10 h-10 bg-primary-600 rounded-xl flex items-center justify-center shadow-lg shadow-primary-600/30">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                </div>
                <span class="text-2xl font-heading font-bold tracking-tight">YT<span class="text-primary-500">Loader</span></span>
            </div>
            <div class="hidden md:flex space-x-8 text-sm font-medium text-white/70">
                <button @click="$el.closest('body').querySelector('#hero').scrollIntoView(); tab = 'video'" class="hover:text-primary-500 transition">Video Downloader</button>
                <button @click="$el.closest('body').querySelector('#hero').scrollIntoView(); tab = 'audio'" class="hover:text-primary-500 transition">Audio Converter</button>
                <a href="#faq" class="hover:text-primary-500 transition">FAQ</a>
            </div>
        </nav>

        <!-- Hero Section -->
        <main id="hero" class="max-w-4xl mx-auto px-4 pt-20 pb-32 text-center">
            <h1 class="text-5xl md:text-7xl font-heading font-extrabold mb-6 tracking-tight">
                Download <span class="gradient-text">Anything</span> <br> from YouTube.
            </h1>
            <p class="text-lg text-white/50 mb-12 max-w-2xl mx-auto leading-relaxed">
                Experience the fastest way to save your favorite YouTube videos and audio files in premium quality. Free, secure, and forever.
            </p>

            <!-- Search Form -->
            <div class="relative group max-w-3xl mx-auto">
                <div class="absolute -inset-1 bg-gradient-to-r from-primary-600 to-purple-600 rounded-2xl blur opacity-25 group-hover:opacity-40 transition duration-1000 group-hover:duration-200"></div>
                <div class="relative flex flex-col sm:flex-row gap-3">
                    <input
                        type="text"
                        x-model="url"
                        placeholder="Paste YouTube link here..."
                        class="flex-1 bg-white/5 border border-white/10 rounded-xl px-6 py-4 text-lg focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all placeholder:text-white/20"
                        @keydown.enter="analyze()"
                    >
                    <button
                        @click="analyze()"
                        :disabled="loading"
                        class="bg-primary-600 hover:bg-primary-500 disabled:opacity-50 text-white px-8 py-4 rounded-xl font-bold text-lg transition shadow-lg shadow-primary-600/20 flex items-center justify-center space-x-2"
                    >
                        <template x-if="!loading">
                            <span>Get Started</span>
                        </template>
                        <template x-if="loading">
                            <div class="flex items-center space-x-2">
                                <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>Analyzing...</span>
                            </div>
                        </template>
                    </button>
                </div>
            </div>

            <!-- Error Message -->
            <div x-show="error" x-cloak class="mt-6 p-4 bg-red-500/10 border border-red-500/20 text-red-500 rounded-xl">
                <span x-text="error"></span>
            </div>

            <!-- Video Info Section -->
            <div x-show="metadata" x-cloak x-transition class="mt-16 text-left">
                <div class="glass rounded-3xl overflow-hidden shadow-2xl">
                    <div class="flex flex-col md:flex-row">
                        <div class="md:w-1/3 relative h-64 md:h-auto">
                            <img :src="metadata.thumbnail" class="absolute inset-0 w-full h-full object-cover" alt="">
                            <div class="absolute bottom-4 right-4 bg-black/80 px-2 py-1 rounded text-xs" x-text="metadata.duration"></div>
                        </div>
                        <div class="md:w-2/3 p-8">
                            <h2 class="text-2xl font-bold mb-2 leading-tight" x-text="metadata.title"></h2>
                            <p class="text-white/40 mb-6 flex items-center">
                                <span class="mr-2" x-text="metadata.channel"></span>
                                <svg class="w-4 h-4 text-blue-500 fill-current" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                            </p>

                            <div class="border-b border-white/10 mb-6 flex space-x-8">
                                <button @click="tab = 'video'" :class="tab === 'video' ? 'text-primary-500 border-b-2 border-primary-500 pb-3' : 'text-white/50 pb-3 hover:text-white'" class="font-bold uppercase tracking-wider text-xs flex items-center transition-all">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    Video
                                </button>
                                <button @click="tab = 'audio'" :class="tab === 'audio' ? 'text-primary-500 border-b-2 border-primary-500 pb-3' : 'text-white/50 pb-3 hover:text-white'" class="font-bold uppercase tracking-wider text-xs flex items-center transition-all">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path></svg>
                                    Audio
                                </button>
                            </div>

                            <div class="max-h-[300px] overflow-y-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="text-white/30 text-[10px] uppercase tracking-widest text-left">
                                            <th class="pb-4 font-medium">Quality</th>
                                            <th class="pb-4 font-medium">Format</th>
                                            <th class="pb-4 font-medium">Size</th>
                                            <th class="pb-4 font-medium text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <template x-for="item in (tab === 'video' ? metadata.formats.video : metadata.formats.audio)" :key="item.format_id">
                                        <tr class="border-b border-white/5 hover:bg-white/5 transition group">
                                            <td class="py-4 font-medium">
                                                <div class="flex items-center space-x-2">
                                                    <span x-text="tab === 'video' ? item.resolution : item.bitrate"></span>
                                                    <template x-if="tab === 'video' && item.resolution.includes('8K')">
                                                        <span class="bg-red-500/10 text-red-500 text-[10px] px-1.5 py-0.5 rounded font-bold">8K</span>
                                                    </template>
                                                    <template x-if="tab === 'video' && item.resolution.includes('4K')">
                                                        <span class="bg-primary-500/10 text-primary-500 text-[10px] px-1.5 py-0.5 rounded font-bold">4K</span>
                                                    </template>
                                                    <template x-if="tab === 'video' && (item.resolution.includes('2K') || item.resolution.includes('1440p'))">
                                                        <span class="bg-purple-500/10 text-purple-500 text-[10px] px-1.5 py-0.5 rounded font-bold">2K</span>
                                                    </template>
                                                    <template x-if="tab === 'video' && item.resolution.includes('1080p')">
                                                        <span class="bg-blue-500/10 text-blue-500 text-[10px] px-1.5 py-0.5 rounded font-bold">FULL HD</span>
                                                    </template>
                                                </div>
                                                <div class="text-[10px] text-white/20 mt-0.5" x-text="tab === 'video' ? (item.vcodec || '') + ' ' + (item.fps ? item.fps + 'fps' : '') : (item.acodec || '')"></div>
                                            </td>
                                            <td class="py-4 text-white/40 uppercase text-[11px]" x-text="item.ext"></td>
                                            <td class="py-4 text-white/40 text-[11px]" x-text="item.filesize"></td>
                                            <td class="py-4 text-right">
                                                <button
                                                    @click="startDownload(item.format_id, tab)"
                                                    class="bg-white/5 group-hover:bg-primary-600 text-white/70 group-hover:text-white transition-all px-4 py-2 rounded-lg font-bold text-[11px] flex items-center ml-auto border border-white/5 group-hover:border-primary-500"
                                                >
                                                    <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                                    Download
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Download Progress Overlay -->
            <div x-show="downloading" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4">
                <div class="glass max-w-md w-full p-8 rounded-3xl text-center">
                    <h3 class="text-2xl font-bold mb-4">Processing Download</h3>
                    <div class="mb-6">
                        <div class="h-4 bg-white/5 rounded-full overflow-hidden mb-2">
                            <div class="h-full bg-primary-600 transition-all duration-300" :style="`width: ${progress}%`"></div>
                        </div>
                        <div class="flex justify-between text-sm text-white/40">
                            <span x-text="downloadStatus">Initializing...</span>
                            <span x-text="`${progress}%` text-white"></span>
                        </div>
                    </div>
                    <template x-if="downloadFileUrl">
                        <a :href="downloadFileUrl" target="_blank" class="block w-full bg-primary-600 hover:bg-primary-500 text-white py-4 rounded-xl font-bold transition">
                            Download Now
                        </a>
                    </template>
                    <button @click="resetDownload()" class="mt-4 text-white/40 hover:text-white transition text-sm">Close</button>
                </div>
            </div>
        </main>

        <!-- Features -->
        <section class="max-w-7xl mx-auto px-4 py-32">
            <div class="grid md:grid-cols-3 gap-8">
                <div class="glass p-8 rounded-3xl hover:border-primary-500/50 transition border border-transparent">
                    <div class="w-12 h-12 bg-blue-500/20 text-blue-500 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Lightning Fast</h3>
                    <p class="text-white/40 leading-relaxed">Parallel processing system for maximum download speeds on any connection.</p>
                </div>
                <div class="glass p-8 rounded-3xl hover:border-primary-500/50 transition border border-transparent">
                    <div class="w-12 h-12 bg-primary-500/20 text-primary-500 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Secure & Safe</h3>
                    <p class="text-white/40 leading-relaxed">No tracking, no malware. Your privacy and security are our top priority.</p>
                </div>
                <div class="glass p-8 rounded-3xl hover:border-primary-500/50 transition border border-transparent">
                    <div class="w-12 h-12 bg-purple-500/20 text-purple-500 rounded-xl flex items-center justify-center mb-6">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">HD Quality</h3>
                    <p class="text-white/40 leading-relaxed">Support for up to 4K resolutions and high bitrate audio formats.</p>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="max-w-4xl mx-auto px-4 py-32 text-left">
            <h2 class="text-4xl font-heading font-bold mb-12 text-center">Frequently Asked <span class="text-primary-500">Questions</span></h2>
            <div class="space-y-4">
                <div x-data="{ open: false }" class="glass rounded-2xl overflow-hidden border border-white/5">
                    <button @click="open = !open" class="w-full p-6 text-left flex justify-between items-center hover:bg-white/5 transition">
                        <span class="font-bold">Is this service free to use?</span>
                        <svg class="w-5 h-5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="open" x-cloak class="p-6 pt-0 text-white/50 leading-relaxed border-t border-white/5 mt-2">
                        Yes, YTLoader is completely free. We do not require any registration or payment to download videos.
                    </div>
                </div>
                <div x-data="{ open: false }" class="glass rounded-2xl overflow-hidden border border-white/5">
                    <button @click="open = !open" class="w-full p-6 text-left flex justify-between items-center hover:bg-white/5 transition">
                        <span class="font-bold">What video qualities are supported?</span>
                        <svg class="w-5 h-5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="open" x-cloak class="p-6 pt-0 text-white/50 leading-relaxed border-t border-white/5 mt-2">
                        We support all qualities provided by YouTube, from 144p up to 4K (2160p) in MP4 and WEBM formats.
                    </div>
                </div>
                <div x-data="{ open: false }" class="glass rounded-2xl overflow-hidden border border-white/5">
                    <button @click="open = !open" class="w-full p-6 text-left flex justify-between items-center hover:bg-white/5 transition">
                        <span class="font-bold">Can I download only the audio?</span>
                        <svg class="w-5 h-5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="open" x-cloak class="p-6 pt-0 text-white/50 leading-relaxed border-t border-white/5 mt-2">
                        Absolutely! You can use our "Audio" tab to select high-quality MP3, M4A, or AAC formats for any video.
                    </div>
                </div>
                <div x-data="{ open: false }" class="glass rounded-2xl overflow-hidden border border-white/5">
                    <button @click="open = !open" class="w-full p-6 text-left flex justify-between items-center hover:bg-white/5 transition">
                        <span class="font-bold">Is there a limit on the number of downloads?</span>
                        <svg class="w-5 h-5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="open" x-cloak class="p-6 pt-0 text-white/50 leading-relaxed border-t border-white/5 mt-2">
                        Currently, there are no limits. You can download as many videos as you like.
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-white/10 py-12 text-center text-white/30 text-sm">
            <p>&copy; 2026 YTLoader. All rights reserved. By using our service you agree to our Terms.</p>
            <p class="mt-2 text-white/20 text-xs">Developed By Sandip Rathva</p>
        </footer>
    </div>

    <script>
        function downloader() {
            return {
                url: '',
                loading: false,
                metadata: null,
                tab: 'video',
                error: null,
                downloading: false,
                progress: 0,
                downloadStatus: '',
                downloadFileUrl: null,
                jobId: null,

                async analyze() {
                    if (!this.url) return;
                    this.loading = true;
                    this.error = null;
                    this.metadata = null;

                    try {
                        const response = await fetch('/analyze', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ url: this.url })
                        });

                        const data = await response.json();
                        if (!response.ok) throw new Error(data.error || 'Something went wrong');

                        this.metadata = data;
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.loading = false;
                    }
                },

                async startDownload(formatId, type) {
                    this.downloading = true;
                    this.progress = 0;
                    this.downloadStatus = 'Queuing download...';
                    this.downloadFileUrl = null;

                    try {
                        const response = await fetch('/download', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                            },
                            body: JSON.stringify({ url: this.url, format_id: formatId, type: type })
                        });

                        const data = await response.json();
                        this.jobId = data.job_id;
                        this.pollStatus();
                    } catch (e) {
                        this.error = e.message;
                        this.downloading = false;
                    }
                },

                async pollStatus() {
                    if (!this.jobId) return;

                    const interval = setInterval(async () => {
                        try {
                            const response = await fetch(`/status/${this.jobId}`);
                            const data = await response.json();

                            if (data.status === 'downloading') {
                                this.progress = data.progress;
                                this.downloadStatus = 'Downloading...';
                            } else if (data.status === 'completed') {
                                this.progress = 100;
                                this.downloadStatus = 'Ready!';
                                this.downloadFileUrl = data.url;
                                clearInterval(interval);
                            } else if (data.status === 'failed') {
                                this.downloadStatus = 'Failed to download';
                                clearInterval(interval);
                            }
                        } catch (e) {
                            console.error(e);
                        }
                    }, 2000);
                },

                resetDownload() {
                    this.downloading = false;
                    this.jobId = null;
                    this.downloadFileUrl = null;
                }
            }
        }
    </script>
</body>
</html>
