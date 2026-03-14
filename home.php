<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly | Move at the Speed of Life</title>
    <link rel="stylesheet" href="./CSS/home.css">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- font awesome cdn -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>

    <header class="w-full px-4 py-4">
        <nav x-data="{ profileOpen: false, mobileOpen: false }"
            class="mx-auto md:h-20 max-w-8xl bg-white/95 backdrop-blur-sm shadow-xl rounded-2xl border border-white/20">

            <div class="px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">

                    <div class="flex items-center">
                        <img src="./main-assets/logo-no-background.png"
                            class="h-20 sm:h-16 md:h-20 lg:h-24 xl:h-32 w-auto transition-all duration-300" alt="Logo"
                            id="logo">
                    </div>

                    <div class="hidden md:flex md:mt-4 space-x-8">
                        <a href="home.html" class="relative flex items-center text-1xl font-semibold text-[#ff5e00] py-1">
                            <i class='bx bxs-home-circle mr-1.5'></i>
                            Home
                            <span class="absolute inset-x-0 bottom-0 h-0.5 bg-[#ff5e00]"></span>
                        </a>

                        <a href="#features"
                            class="group relative flex items-center text-1xl font-medium text-gray-600 hover:text-[#ff5e00] transition-colors duration-300 py-1">
                            <i class='bx bxs-zap mr-1.5 transition-transform group-hover:scale-110'></i>
                            Features
                            <span
                                class="absolute inset-x-0 bottom-0 h-0.5 bg-[#ff5e00] transform scale-x-0 origin-left transition-transform duration-300 group-hover:scale-x-100"></span>
                        </a>

                        <a href="#"
                            class="group relative flex items-center text-1xl font-medium text-gray-600 hover:text-[#ff5e00] transition-colors duration-300 py-1">
                            <i class='bx bxs-envelope mr-1.5 transition-transform group-hover:rotate-12'></i>
                            Contact us
                            <span
                                class="absolute inset-x-0 bottom-0 h-0.5 bg-[#ff5e00] transform scale-x-0 origin-left transition-transform duration-300 group-hover:scale-x-100"></span>
                        </a>
                    </div>

                    <div class="flex items-center space-x-4">
                        <button class="text-gray-500 md:mt-6 hover:text-[#ff5e00] transition">
                            <i class='bx bx-bell text-xl'></i>
                        </button>

                        <div class="relative">
                            <button @click="profileOpen = !profileOpen" @click.away="profileOpen = false"
                                class="flex items-center focus:outline-none">
                                <img class="h-9 w-9 md:mt-5 rounded-full border-2 border-[#ff5e00]/20"
                                    src="https://ui-avatars.com/api/?name=User&background=ff5e00&color=fff" alt="User">
                            </button>

                            <div x-show="profileOpen" style="display: none;" x-transition
                                class="absolute right-0 mt-3 w-48 bg-white rounded-xl shadow-2xl py-2 border border-gray-100 z-50">
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                                    <i class='bx bx-user mr-2'></i> Your Profile
                                </a>
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-orange-50">
                                    <i class='bx bx-cog mr-2'></i> Settings
                                </a>
                                <hr class="my-1 border-gray-100">
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class='bx bx-log-out mr-2'></i> Sign Out
                                </a>
                            </div>
                        </div>

                        <button @click="mobileOpen = !mobileOpen" class="md:hidden text-gray-600">
                            <i class='bx' :class="mobileOpen ? 'bx-x' : 'bx-menu-alt-right'"
                                style="font-size: 1.5rem;"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div x-show="mobileOpen" x-transition class="md:hidden border-t border-gray-100 p-4 space-y-2 flex flex-col items-center">
                <a href="#" class="relative flex items-center text-1xl font-semibold text-[#ff5e00] py-1">
                    <i class='bx bxs-home-circle mr-2'></i> Home
                    <span class="absolute inset-x-0 bottom-0 h-0.5 bg-[#ff5e00]"></span>
                </a>

                <a href="#"
                    class="group relative flex items-center text-1xl font-medium text-gray-600 hover:text-[#ff5e00] transition-colors duration-300 py-1">
                    <i class='bx bxs-zap mr-2'></i> Features
                    <span
                        class="absolute inset-x-0 bottom-0 h-0.5 bg-[#ff5e00] transform scale-x-0 origin-left transition-transform duration-300 group-hover:scale-x-100"></span>
                </a>

                <a href="#"
                    class="group relative flex items-center text-1xl font-medium text-gray-600 hover:text-[#ff5e00] transition-colors duration-300 py-1">
                    <i class='bx bxs-envelope mr-2'></i> Contact us
                    <span
                        class="absolute inset-x-0 bottom-0 h-0.5 bg-[#ff5e00] transform scale-x-0 origin-left transition-transform duration-300 group-hover:scale-x-100"></span>
                </a>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="relative bg-white  dark:bg-gray-900 overflow-hidden rounded-lg mt-14">

        <video autoplay muted loop playsinline
            class="absolute top-0 left-0 min-w-full min-h-full object-cover z-0 opacity-1">
            <source src="./main-assets/5233_New_York_NYC_1920x1080.mp4" type="video/mp4">
            Your browser does not support the video tag.
        </video>

        <div class="absolute inset-0 bg-white/40 dark:bg-gray-900/60 z-10"></div>

        <div class="relative z-20 py-8 px-4 mx-auto max-w-screen-xl text-center lg:py-16 lg:px-12">
            <a href="#"
                class="inline-flex justify-between items-center py-1 px-1 pr-4 mb-7 text-sm text-gray-700 bg-gray-100 rounded-full dark:bg-gray-800 dark:text-white hover:bg-gray-200 dark:hover:bg-gray-700"
                role="alert">
                <span class="text-xs bg-[#e65500] rounded-full text-white px-4 py-1.5 mr-3">New</span>
                <span class="text-sm font-medium">Speedly Pro is out! See what's new</span>
                <svg class="ml-2 w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd"
                        d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"
                        clip-rule="evenodd"></path>
                </svg>
            </a>
            <div
                class="inline-block px-8 py-4 mb-6 bg-white/90 border-2 border-gray-100 rounded-full shadow-lg backdrop-blur-sm">
                <h1 class="text-4xl font-extrabold tracking-tight leading-none md:text-5xl lg:text-6xl 
    animate-gradient bg-gradient-to-r from-black via-[#e65500] to-[#333333] 
    bg-[length:200%_auto] bg-clip-text text-transparent">
                    We accelerate the city's movement
                </h1>
            </div>
            <p class="mb-8 text-lg font-normal text-gray-900 lg:text-xl sm:px-16 xl:px-48 dark:text-gray-100"
                style="font-weight: 800; text-shadow: 40px 0px 40px rgba(255, 255, 255, 0.8);">
                Here at Speedly we focus on routes where efficiency, reliability, and speed can unlock seamless
                travel and drive urban connection.
            </p>
            <div class="flex flex-col mb-8 lg:mb-16 space-y-4 sm:flex-row sm:justify-center sm:space-y-0 sm:space-x-6">

                <?php
                // Determine the link based on session
                if (!isset($_SESSION) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
                    $link = 'form.php';
                } else {
                    $link = 'book-ride.php';
                }
                ?>

                <a href="<?php echo $link; ?>"
                    class="group relative inline-flex justify-center items-center py-3 px-8 text-sm font-bold tracking-wide text-center text-white rounded-full bg-[#e65500] shadow-lg shadow-orange-900/20 transition-all duration-300 hover:bg-[#cc4c00] hover:-translate-y-1 hover:shadow-xl hover:shadow-orange-900/30 focus:ring-4 focus:ring-orange-300">
                    Book a Ride
                    <i class='bx bx-right-arrow-alt ml-2 text-xl transition-transform duration-300 group-hover:translate-x-1'></i>
                </a>

                <a href="#"
                    class="inline-flex justify-center items-center py-3 px-8 text-sm font-bold tracking-wide text-center text-gray-900 rounded-full border border-gray-200 bg-white/90 backdrop-blur-md shadow-sm transition-all duration-300 hover:bg-white hover:-translate-y-1 hover:shadow-lg focus:ring-4 focus:ring-gray-100 dark:text-white dark:bg-gray-800/90 dark:border-gray-700 dark:hover:bg-gray-800">
                    <i class='bx bx-play-circle mr-2 text-xl text-[#e65500]'></i>
                    How it Works
                </a>

            </div>

        </div>
    </section>

    <!-- Services -->

    <div class="flex justify-center items-center flex-col mt-14 px-4">
        <h2 class="
    /* Colors & Border */
    text-[#e65500] bg-white border-4 border-[#e65500] rounded-full italic font-black
    
    /* Responsive Text Size */
    text-1xl sm:text-2xl md:text-3xl lg:text-4xl
    
    /* Responsive Shadow (Scales from 4px to 8px) */
    shadow-[4px_4px_0px_0px_black] sm:shadow-[6px_6px_0px_0px_black] md:shadow-[8px_8px_0px_0px_black]
    
    /* Responsive Padding & Margin */
    px-6 py-2 sm:px-10 sm:py-3 md:px-12 md:py-4 mb-6 md:mb-10
    
    /* Layout */
    inline-block text-center max-w-full">
            OUR SERVICES
        </h2>

        <div class="flex flex-wrap justify-center gap-8 w-full max-w-screen- mt-10">
            <div id="service1"
                class="relative group flex flex-col justify-end items-center w-80 h-96 rounded-2xl overflow-hidden shadow-xl transition-transform duration-300 hover:-translate-y-2 bg-cover bg-center"
                style="background-image: url('./main-assets/book-ride-1.jpg');">

                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

                <div class="relative z-10 p-6 text-center">
                    <p class="text-white font-medium mb-4">Premium Airport Transfers</p>
                    <button
                        class="bg-[#e65500] text-white font-bold py-2 px-6 rounded-lg hover:bg-[#ff6600] transition-colors">
                        BOOK RIDE
                    </button>
                </div>
            </div>

            <div id="service2"
                class="relative group flex flex-col justify-end items-center w-80 h-96 rounded-2xl overflow-hidden shadow-xl transition-transform duration-300 hover:-translate-y-2 bg-cover bg-center"
                style="background-image: url('./main-assets/book-ride-2.jpg');">

                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

                <div class="relative z-10 p-6 text-center">
                    <p class="text-white font-medium mb-4">City-to-City Travel</p>
                    <button
                        class="bg-[#e65500] text-white font-bold py-2 px-6 rounded-lg hover:bg-[#ff6600] transition-colors">
                        BOOK RIDE
                    </button>
                </div>
            </div>

            <div id="service3"
                class="relative group flex flex-col justify-end items-center w-80 h-96 rounded-2xl overflow-hidden shadow-xl transition-transform duration-300 hover:-translate-y-2 bg-cover bg-center"
                style="background-image: url('./main-assets/book-ride-3.jpg');">

                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

                <div class="relative z-10 p-6 text-center">
                    <p class="text-white font-medium mb-4">Corporate Chauffeur</p>
                    <button
                        class="bg-[#e65500] text-white font-bold py-2 px-6 rounded-lg hover:bg-[#ff6600] transition-colors">
                        BOOK RIDE
                    </button>
                </div>
            </div>


        </div>

        <div id="explore-section"
            class="relative group flex flex-col justify-center items-center w-full h-96 rounded-2xl overflow-hidden shadow-xl transition-transform duration-300 hover:-translate-y-2 bg-cover bg-center mt-14"
            style="background-image: url('./main-assets/explore.jpg');">

            <div class="absolute inset-0 bg-black/40"></div>

            <p class="relative z-10 text-[#e65500] text-center text-3xl md:text-5xl font-extrabold px-4"
                style="text-shadow: 4px 4px 15px black;">
                <span id="typing-text"></span><span class="animate-pulse border-r-4 border-[#e65500] ml-1"></span>
            </p>
        </div>

    </div>

    <!-- About Us -->
    <div class="flex justify-center items-center mt-14 flex-col">
        <h2 class="
    /* Colors & Border */
    text-[#e65500] bg-white border-4 border-[#e65500] rounded-full italic font-black
    
    /* Responsive Text Size */
    text-1xl sm:text-2xl md:text-3xl lg:text-4xl
    
    /* Responsive Shadow (Scales from 4px to 8px) */
    shadow-[4px_4px_0px_0px_black] sm:shadow-[6px_6px_0px_0px_black] md:shadow-[8px_8px_0px_0px_black]
    
    /* Responsive Padding & Margin */
    px-6 py-2 sm:px-10 sm:py-3 md:px-12 md:py-4 mb-6 md:mb-10
    
    /* Layout */
    inline-block text-center max-w-full">
            ABOUT US
        </h2>
        <div class="flex flex-col md:flex-row items-center justify-between gap-12 max-w-6xl mx-auto px-6 py-20">

            <div class="w-full md:w-1/2">
                <h2 class="text-6xl md:text-8xl font-black text-gray-900 tracking-tighter leading-none">
                    SPEEDLY <span class="text-[#e65500]">.</span>
                </h2>
                <p class="mt-6 text-[#ffff] font-bold uppercase tracking-[0.2em] text-sm">
                    Urban Mobility Redefined
                </p>
            </div>

            <div class="w-full md:w-1/2 border-l-4 border-[#e65500] pl-8">
                <p class="text-xl md:text-2xl font-medium text-gray-700 leading-relaxed">
                    Speedly is the <span
                        class="text-black font-bold underline decoration-[#e65500] decoration-4">premier mobility
                        super-app.</span>
                    We build cities for people, not traffic.
                </p>
                <p class="mt-4 text-black leading-relaxed">
                    From instant ride-hailing and shared fleets to scooters and lightning-fast delivery—we provide a
                    better alternative to the private car for every journey.
                </p>

                <button
                    class="mt-8 px-8 py-3 bg-black text-white font-bold rounded-full hover:bg-[#e65500] transition-all duration-300 transform hover:translate-x-2 shadow-lg shadow-orange-200">
                    Learn More →
                </button>
            </div>
        </div>

    </div>

    <!-- Features -->
    <div id="features" class="max-w-6xl mx-auto px-6 py-20 space-y-24 flex justify-center items-center flex-col">

        <h2 class="
    /* Colors & Border */
    text-[#e65500] bg-white border-4 border-[#e65500] rounded-full italic font-black
    
    /* Responsive Text Size */
    text-1xl sm:text-2xl md:text-3xl lg:text-4xl
    
    /* Responsive Shadow (Scales from 4px to 8px) */
    shadow-[4px_4px_0px_0px_black] sm:shadow-[6px_6px_0px_0px_black] md:shadow-[8px_8px_0px_0px_black]
    
    /* Responsive Padding & Margin */
    px-6 py-2 sm:px-10 sm:py-3 md:px-12 md:py-4 mb-6 md:mb-10
    
    /* Layout */
    inline-block text-center max-w-full">
            OUR FEATURES
        </h2>

        <div class="flex flex-col md:flex-row items-center gap-12 group">
            <div class="w-full md:w-1/2 overflow-hidden rounded-3xl shadow-2xl">
                <img src="./main-assets/driver.png" alt="Luxury Interior"
                    class="w-full h-80 object-cover transition-transform duration-500 group-hover:scale-110">
            </div>

            <div class="w-full md:w-1/2 space-y-6">
                <div
                    class="inline-block px-4 py-1 bg-orange-100 text-[#e65500] rounded-full text-sm font-bold tracking-widest uppercase">
                    Book a Ride at comfort
                </div>
                <h3 class="text-4xl font-black text-gray-900 leading-tight">
                    Your living room, <br><span class="text-[#ffff]">on wheels.</span>
                </h3>
                <p class="text-black text-lg leading-relaxed">
                    Why wait on the curb? Request a premium Speedly ride from the comfort of your sofa and watch your
                    driver arrive in real-time.
                </p>
                <button
                    class="group flex items-center gap-3 bg-[#e65500] text-white font-bold py-4 px-8 rounded-xl hover:bg-black transition-all duration-300 shadow-lg shadow-orange-200">
                    BOOK NOW
                    <span class="transition-transform group-hover:translate-x-2">→</span>
                </button>
            </div>
        </div>

        <div class="flex flex-col md:flex-row-reverse items-center gap-12 group">
            <div class="w-full md:w-1/2 overflow-hidden rounded-3xl shadow-2xl">
                <img src="./main-assets/travel.jpg" alt="Speedly App"
                    class="w-full h-80 object-cover transition-transform duration-500 group-hover:scale-110">
            </div>

            <div class="w-full md:w-1/2 space-y-6">
                <div
                    class="inline-block px-4 py-1 bg-orange-100 text-[#e65500] rounded-full text-sm font-bold tracking-widest uppercase">
                    Travel to a long distance safely
                </div>
                <h3 class="text-4xl font-black text-gray-900 leading-tight">
                    Travel with <br><span class="text-[#ffff]">Total Peace of Mind.</span>
                </h3>
                <p class="text-black text-lg leading-relaxed">
                    Every Speedly captain is vetted and tracked. Share your live trip status with loved ones with a
                    single tap.
                </p>
                <button
                    class="group flex items-center gap-3 bg-black text-white font-bold py-4 px-8 rounded-xl hover:bg-[#e65500] transition-all duration-300 shadow-lg">
                    SECURE YOUR RIDE
                    <span class="transition-transform group-hover:translate-x-2">→</span>
                </button>
            </div>
        </div>

        <div class="flex flex-col md:flex-row items-center gap-12 group">
            <div class="w-full md:w-1/2 overflow-hidden rounded-3xl shadow-2xl">
                <img src="./main-assets/office.jpg" alt="Luxury Interior"
                    class="w-full h-80 object-cover transition-transform duration-500 group-hover:scale-110">
            </div>

            <div class="w-full md:w-1/2 space-y-6">
                <div
                    class="inline-block px-4 py-1 bg-orange-100 text-[#e65500] rounded-full text-sm font-bold tracking-widest uppercase">
                    Drive with Speedly
                </div>

                <h3 class="text-4xl font-black text-gray-900 leading-tight">
                    Your car, <br><span class="text-[#ffff]">your office.</span>
                </h3>

                <p class="text-black text-lg leading-relaxed">
                    Turn your miles into money. With Speedly, you’re in the driver’s seat of your own business—choose
                    your own hours, earn competitive weekly payouts, and get 24/7 support.
                </p>

                <button
                    class="group flex items-center gap-3 bg-[#e65500] text-white font-bold py-4 px-10 rounded-xl hover:bg-black transition-all duration-300 shadow-lg shadow-orange-200">
                    START EARNING
                    <span class="transition-transform group-hover:translate-x-2">→</span>
                </button>
            </div>
        </div>

        <div class="flex flex-col md:flex-row-reverse items-center gap-12 group">
            <div class="w-full md:w-1/2 overflow-hidden rounded-3xl shadow-2xl bg-gray-200">
                <img src="https://images.unsplash.com/photo-1534536281715-e28d76689b4d?q=80&w=800"
                    alt="Speedly Customer Support"
                    class="w-full h-80 object-cover transition-transform duration-500 group-hover:scale-105">
            </div>

            <div class="w-full md:w-1/2 space-y-6">
                <div
                    class="inline-block px-4 py-1 bg-red-100 text-red-600 rounded-full text-sm font-bold tracking-widest uppercase">
                    24/7 Resolution Center
                </div>

                <h3 class="text-4xl font-black text-gray-900 leading-tight">
                    Journey didn't go <br><span class="text-[#ffff]">as planned?</span>
                </h3>

                <p class="text-black text-lg leading-relaxed">
                    Your safety and satisfaction are our top priorities. If you encountered an issue, a lost item, or a
                    professional concern during your Speedly ride, let us know immediately. We’re here to make it right.
                </p>

                <button
                    class="group flex items-center gap-3 bg-black text-white font-bold py-4 px-10 rounded-xl hover:bg-[#e65500] transition-all duration-300 shadow-lg">
                    FILE A COMPLAINT
                    <span class="transition-transform group-hover:translate-x-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                </button>
            </div>
        </div>

    </div>

    <!-- Download our Apps -->

    <div class="max-w-6xl mx-auto px-6 py-20">
        <div class="relative overflow-hidden bg-[#1a1a1b] rounded-[3rem] shadow-2xl">

            <div class="absolute -top-24 -right-24 w-96 h-96 bg-[#e65500] opacity-20 blur-[100px]"></div>
            <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-[#e65500] opacity-10 blur-[100px]"></div>

            <div class="relative z-10 flex flex-col md:flex-row items-center justify-between p-12 md:p-20">

                <div class="w-full md:w-1/2 space-y-8 text-center md:text-left">
                    <div
                        class="inline-block px-4 py-1 bg-[#e65500]/10 text-[#e65500] border border-[#e65500]/20 rounded-full text-sm font-bold tracking-widest uppercase">
                        Available on iOS & Android
                    </div>

                    <h2 class="text-4xl md:text-6xl font-black text-white leading-tight tracking-tighter">
                        Ready to move? <br>
                        <span class="text-[#e65500]">Get Speedly today.</span>
                    </h2>

                    <p class="text-gray-400 text-lg md:pr-10">
                        Join over 1 million users moving smarter every day. Book rides, track deliveries, and manage
                        your journey all in one place.
                    </p>

                    <div class="flex flex-wrap justify-center md:justify-start gap-4">
                        <a href="#"
                            class="flex items-center gap-3 bg-white text-black px-6 py-3 rounded-2xl hover:bg-[#e65500] hover:text-white transition-all duration-300 transform hover:-translate-y-1">
                            <svg class="w-20 h-20" fill="currentColor" viewBox="0 0 384 512">
                                <path
                                    d="M318.7 268.7c-.2-36.7 16.4-64.4 50-84.8-18.8-26.9-47.2-41.7-84.7-44.6-35.5-2.8-74.3 20.7-88.5 20.7-15 0-49.4-19.7-76.4-19.7C63.3 141.2 4 184.8 4 273.5q0 39.3 14.4 81.2c12.8 36.7 59 126.7 107.2 125.2 25.2-.6 43-17.9 75.8-17.9 31.8 0 48.3 17.9 76.4 17.9 48.6-.7 90.4-82.5 102.6-119.3-65.2-30.7-61.7-90-61.7-91.9zm-56.6-164.2c27.3-32.4 24.8-61.9 24-72.5-24.1 1.4-52 16.4-67.9 34.9-17.5 19.8-27.8 44.3-25.6 71.9 26.1 2 49.9-11.4 69.5-34.3z" />
                            </svg>
                            <div class="text-left">
                                <p class="text-[10px] uppercase leading-none">Download it for :</p>
                                <p class="text-xl font-bold leading-tight">IOS devices</p>
                            </div>
                        </a>

                        <a href="#"
                            class="flex items-center gap-3 bg-white text-black px-6 py-3 rounded-2xl hover:bg-[#e65500] hover:text-white transition-all duration-300 transform hover:-translate-y-1">
                            <svg class="w-20 h-20" fill="currentColor" viewBox="0 0 512 512">
                                <path
                                    d="M325.3 234.3L104.6 13l280.8 161.2-60.1 60.1zM47 0C34 6.8 25.3 19.2 25.3 35.3v441.3c0 16.1 8.7 28.5 21.7 35.3l256.6-256L47 0zm425.2 225.6l-58.9-34.1-65.7 64.5 65.7 64.5 60.1-34.7c24.5-14.2 24.5-37.1-1.2-51.2zM104.6 499l280.8-161.2-60.1-60.1L104.6 499z" />
                            </svg>
                            <div class="text-left">
                                <p class="text-[10px] uppercase leading-none">Download it for :</p>
                                <p class="text-xl font-bold leading-tight">Android devices</p>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="w-full md:w-1/2 mt-12 md:mt-0 flex justify-center relative">
                    <div
                        class="w-64 h-[450px] bg-[#e65500] rounded-[3rem] border-8 border-black shadow-2xl relative overflow-hidden transform rotate-6 hover:rotate-0 transition-transform duration-500">
                        <div class="absolute top-0 w-full h-8 bg-black flex justify-center">
                            <div class="w-20 h-4 bg-black rounded-b-xl"></div>
                        </div>
                        <div class="p-6 mt-10">
                            <div class="w-full h-4 bg-white/20 rounded mb-4"></div>
                            <div class="w-2/3 h-4 bg-white/20 rounded mb-8"></div>
                            <div class="w-full h-32 bg-white/10 rounded-2xl mb-4 flex items-center justify-center">
                                <span class="text-white opacity-20 text-4xl font-black">SPEEDLY</span>
                            </div>
                            <div class="space-y-3">
                                <div class="w-full h-10 bg-white rounded-lg"></div>
                                <div class="w-full h-10 bg-white rounded-lg opacity-50"></div>
                            </div>
                        </div>
                    </div>
                    <div
                        class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-white/5 rounded-full -z-10">
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Footer -->

    <footer
        class="bg-white mt-14 text-gray-900 py-12 md:py-16 px-4 sm:px-6 md:px-8 lg:px-20 border-t border-gray-100 rounded-3xl">
        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row justify-between items-start gap-12">

            <div class="w-full lg:w-1/4 space-y-6">
                <a href="#" class="flex items-center group">
                    <img src="./main-assets/logo-no-background.png" alt="Speedly Logo"
                        class="h-26 w-auto object-contain transition-transform duration-300 group-hover:scale-110">
                </a>
                <p class="text-sm text-gray-500 leading-relaxed">
                    Join our newsletter for regular updates on new features and city launches.
                </p>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="email" placeholder="example@email.com"
                        class="bg-gray-50 text-gray-900 border border-gray-200 px-4 py-3 rounded-xl w-full sm:flex-1 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-[#e65500]/20 focus:border-[#e65500]" />
                    <button
                        class="bg-[#e65500] text-white px-6 py-3 rounded-xl font-bold text-sm hover:bg-black transition-all duration-300 shadow-lg shadow-orange-100">
                        Subscribe
                    </button>
                </div>
            </div>

            <div class="w-full lg:w-2/3 flex flex-wrap md:flex-nowrap justify-between gap-8 md:gap-12">
                <div class="flex-1 min-w-[140px]">
                    <h3 class="font-bold text-sm uppercase tracking-wider mb-6 text-gray-900">Services</h3>
                    <ul class="space-y-4 text-sm text-gray-500">
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Ride Hailing</a></li>
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Food Delivery</a></li>
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Courier Services</a></li>
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Business Accounts</a></li>
                    </ul>
                </div>

                <div class="flex-1 min-w-[140px]">
                    <h3 class="font-bold text-sm uppercase tracking-wider mb-6 text-gray-900">Support</h3>
                    <ul class="space-y-4 text-sm text-gray-500">
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Help Center</a></li>
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Safety</a></li>
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Log a Complaint</a></li>
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">City Coverage</a></li>
                    </ul>
                </div>

                <div class="flex-1 min-w-[140px]">
                    <h3 class="font-bold text-sm uppercase tracking-wider mb-6 text-gray-900">Company</h3>
                    <ul class="space-y-4 text-sm text-gray-500">
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">About Us</a></li>
                        <li class="flex items-center gap-2">
                            <a href="#" class="hover:text-[#e65500] transition-colors">Careers</a>
                            <span
                                class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-100 text-[#e65500]">HIRING</span>
                        </li>
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-[#e65500] transition-colors">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            class="max-w-7xl mx-auto mt-12 md:mt-16 pt-8 border-t border-gray-100 flex flex-col md:flex-row justify-between items-center gap-6">
            <p class="text-gray-400 text-xs sm:text-sm order-2 md:order-1">© 2026 Speedly Mobility Solutions</p>

            <div class="flex gap-6 order-1 md:order-2">
                <a href="#" class="text-gray-400 hover:text-[#e65500] transition-colors">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path
                            d="M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z" />
                    </svg>
                </a>
                <a href="#" class="text-gray-400 hover:text-[#e65500] transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z" />
                        <rect width="4" height="12" x="2" y="9" />
                        <circle cx="4" cy="4" r="2" />
                    </svg>
                </a>
                <a href="#" class="text-gray-400 hover:text-[#e65500] transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
                        <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
                        <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
                    </svg>
                </a>
            </div>
        </div>
    </footer>




    <script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>
    <script src="./JS/home.js"></script>
</body>

</html>