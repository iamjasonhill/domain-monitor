<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Domain Monitor - Welcome</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-gray-50 dark:bg-gray-900">
        <div class="min-h-screen flex flex-col">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow-sm">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center">
                            <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                            </svg>
                            <h1 class="ml-3 text-2xl font-bold text-gray-900 dark:text-white">Domain Monitor</h1>
                        </div>
                        <div>
                            <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Log In
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-grow">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <!-- Hero Section -->
                    <div class="text-center mb-12">
                        <h2 class="text-4xl font-bold text-gray-900 dark:text-white mb-4">
                            Welcome to Domain Monitor
                        </h2>
                        <p class="text-xl text-gray-600 dark:text-gray-400 mb-8 max-w-3xl mx-auto">
                            Comprehensive domain monitoring, health checks, and management for all your domains in one place.
                        </p>
                        <div class="flex justify-center gap-4">
                            <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Log In to Dashboard
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center px-6 py-3 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-sm text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Register
                                </a>
                            @endif
                        </div>
                    </div>

                    <!-- Features Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-12">
                        <!-- Feature 1 -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">Health Monitoring</h3>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">
                                Automated HTTP, SSL, and DNS health checks to ensure your domains are always up and running.
                            </p>
                        </div>

                        <!-- Feature 2 -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">Expiry Tracking</h3>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">
                                Never miss a domain renewal with automatic expiry tracking and alerts for domains expiring soon.
                            </p>
                        </div>

                        <!-- Feature 3 -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                                    </svg>
                                </div>
                                <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">Platform Detection</h3>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">
                                Automatically detect platform types (WordPress, Laravel, Next.js, Shopify, etc.) for each domain.
                            </p>
                        </div>

                        <!-- Feature 4 -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">DNS Management</h3>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">
                                Sync and manage DNS records directly from Synergy Wholesale for Australian TLD domains.
                            </p>
                        </div>

                        <!-- Feature 5 -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">Real-time Alerts</h3>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">
                                Get instant notifications when domains fail health checks or are approaching expiry.
                            </p>
                        </div>

                        <!-- Feature 6 -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <h3 class="ml-4 text-lg font-semibold text-gray-900 dark:text-white">Analytics Dashboard</h3>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400">
                                Comprehensive dashboard with statistics, recent checks, and quick access to all your domains.
                            </p>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 mt-auto">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                        <p>Domain Monitor - Comprehensive domain management and monitoring</p>
                        <p class="mt-2">
                            <a href="{{ route('login') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Log In</a>
                            @if (Route::has('register'))
                                <span class="mx-2">|</span>
                                <a href="{{ route('register') }}" class="text-blue-600 dark:text-blue-400 hover:underline">Register</a>
                            @endif
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
