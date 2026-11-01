<?php

use function Livewire\Volt\{state, layout, with};

layout('layouts.app');

with(function () {
    $courses = [
        [
            'id' => 1,
            'title' => 'Introduction to IoT',
            'description' => 'The Internet of Things (IoT) is a fast-growing area of technology...',
            'instructor' => 'Ir. Jihad Fafa',
            'sections' => [
                [
                    'id' => 101,
                    'title' => 'Basic Concept',
                    'lessons' => [
                        ['title' => 'Apa itu IoT?', 'type' => 'Video'],
                        ['title' => 'Sejarah IoT', 'type' => 'Materi'],
                    ]
                ],
                [
                    'id' => 102,
                    'title' => 'Networking & Devices',
                    'lessons' => [
                        ['title' => 'Macam-macam Sensor', 'type' => 'Video'],
                        ['title' => 'Jaringan IoT', 'type' => 'Quiz'],
                    ]
                ]
            ]
        ],
        [
            'id' => 2,
            'title' => 'Advanced IoT',
            'description' => 'Deep dive into advanced IoT concepts, security protocols...',
            'instructor' => 'Ir. Jihad Fafa',
            'sections' => [
                [
                    'id' => 201,
                    'title' => 'Cloud Integration',
                    'lessons' => [
                        ['title' => 'Connecting to AWS IoT', 'type' => 'Video'],
                    ]
                ],
                [
                    'id' => 202,
                    'title' => 'Security',
                    'lessons' => [
                        ['title' => 'Securing Endpoints', 'type' => 'Video'],
                    ]
                ]
            ]
        ]
    ];

    // 1. State awal untuk Course (Hanya index 0 yang true)
    $initialCourseState = collect($courses)->mapWithKeys(function ($course, $index) {
        return [$course['id'] => $index === 0];
    })->toArray();

    // 2. State awal untuk Section (Semuanya tertutup / false secara default)
    $initialSectionState = collect($courses)->flatMap(function ($course) {
        return $course['sections'];
    })->mapWithKeys(function ($section) {
        return [$section['id'] => false];
    })->toArray();

    return [
        'courses' => $courses,
        'initialCourseState' => $initialCourseState,
        'initialSectionState' => $initialSectionState,
    ];
});

?>

    <div class="min-h-screen text-slate-800 pb-12">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <div class="lg:col-span-2 space-y-8">
                
                <div>
                    <h1 class="text-5xl font-bold font-heading text-text-blue mb-4">Introduction to IoT and Digital Transformation</h1>
                    <p class="text-text-gray font-body mb-4 text-md">
                        Billions of devices connect to the network every day. Learn how IoT is digitally transforming the world and opening up exciting new jobs.
                    </p>
                    
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <span class="text-text-gray text-xs ml-2">Topic:</span>
                        <span class="font-caption text-xs bg-secondary2 text-text-blue px-2.5 py-1 rounded border border-primary font-medium">Programming</span>
                        <span class="text-text-gray text-xs ml-2">Level:</span>
                        <span class="font-caption text-xs bg-secondary2 text-text-blue px-2.5 py-1 rounded border border-primary font-medium">Beginner</span>
                        <span class="text-text-gray text-xs ml-2">Type:</span>
                        <span class="font-caption text-xs bg-secondary2 text-text-blue px-2.5 py-1 rounded border border-primary font-medium">Program Bundling</span>
                        <span class="text-text-gray text-xs ml-2">Contain:</span>
                        <span class="font-caption text-xs bg-secondary2 text-text-blue px-2.5 py-1 rounded border border-primary font-medium">5 Courses</span>
                    </div>

                    <div class="flex items-center gap-4 text-sm font-caption text-text-gray border-b border-slate-200 pb-6 font-extralight">
                        <div class="flex items-center gap-1 font-caption">
                            <svg class="w-4 h-4 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                            4.8
                        </div>
                        <div class="flex items-center gap-1 mx-2">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg> 
                            2,500 Learner Enrolled
                        </div>
                        <div class="flex items-center gap-1 mx-2">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg> 
                            Last updated 10/25
                        </div>
                        <div class="flex items-center gap-1 mx-2">
                            <svg class="w-4 h-4 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg> 
                            English, Bahasa Indonesia
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h2 class="font-heading text-3xl font-bold text-text-blue mb-8">About this program</h2>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-stroke border-2 p-16">
                        
                        <p class="text-base font-body text-text-gray leading-normal mb-8">
                            The Internet of Things (IoT) is a fast-growing area of technology, with new companies, products, and job opportunities springing up all over the world. Discover how IoT, along with emerging technologies such as data analytics and artificial intelligence and cybersecurity, are digitally transforming industries and expanding career opportunities.
                        </p>

                        
                        

                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-8">
                            <svg class="w-7 h-7 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg> 
                            Date and Time Information
                        </h3>

                        <div class="relative max-w-4xl mb-12">
                            
                            <div class="absolute top-6 bottom-6 w-0.5 bg-primary left-[111px] md:left-[183px] z-0"></div>

                            <div class="space-y-6">
                                
                                <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                    <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                        5 Januari 2026 -<br>18 Januari 2026
                                    </div>
                                    
                                    <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </div>
                                    
                                    <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                        <div class="font-semibold font-body text-text-blue mb-1">Masa Pendaftaran</div>
                                        <div class="mt-2 text-sm text-text-gray font-body">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus. Nam massa nisl, pretium quis.</div>
                                    </div>
                                </div>

                                <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                    <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                        1 Februari 2026<br>08.30 WIB
                                    </div>
                                    <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                                    </div>
                                    <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                        <div class="font-semibold font-body text-text-blue mb-1">Pembukaan Program</div>
                                        <div class="mt-2 text-sm text-text-gray font-body">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus. Nam massa nisl, pretium quis.</div>
                                    </div>
                                </div>

                                <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                    <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                        1 Februari 2026 -<br>12 Februari 2026
                                    </div>
                                    <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M5 5l1.5 1.5"/><path d="M17.5 17.5L19 19"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M5 19l1.5-1.5"/><path d="M17.5 6.5L19 5"/>
                                        </svg>
                                    </div>
                                    <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                        <div class="font-semibold font-body text-text-blue mb-1">Masa Pembelajaran Course : Introduction to IOT Course</div>
                                        <div class="mt-2 text-sm text-text-gray font-body">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus. Nam massa nisl, pretium quis.</div>
                                    </div>
                                </div>

                                <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                    <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                        13 Februari 2026 -<br>28 Februari 2026
                                    </div>
                                    <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M5 5l1.5 1.5"/><path d="M17.5 17.5L19 19"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M5 19l1.5-1.5"/><path d="M17.5 6.5L19 5"/>
                                        </svg>
                                    </div>
                                    <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                        <div class="font-semibold font-body text-text-blue mb-1">Masa Pembelajaran Course : Internet of Things Component</div>
                                        <div class="mt-2 text-sm text-text-gray font-body">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus. Nam massa nisl, pretium quis.</div>
                                    </div>
                                </div>

                                <div class="relative flex items-center gap-3 md:gap-6 z-10">
                                    <div class="w-[80px] md:w-[140px] shrink-0 text-right text-xs md:text-sm font-bold text-text-blue">
                                        1 Maret 2026
                                    </div>
                                    <div class="w-10 h-10 rounded-full border-2 border-primary bg-white text-primary flex items-center justify-center shrink-0 shadow-sm">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                                    </div>
                                    <div class="bg-secondary3 border border-primary rounded-xl p-4 flex-1">
                                        <div class="font-semibold font-body text-text-blue mb-1">Penutup</div>
                                        <div class="mt-2 text-sm text-text-gray font-body">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus. Nam massa nisl, pretium quis.</div>
                                    </div>
                                </div>

                            </div>
                        </div>




                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-4">
                            <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg> 
                            Location Information
                        </h3>
                        <div class="flex gap-4 items-start mb-8 text-sm">
                            <div class="flex-1 ml-12">
                                <p class="font-semibold font-body text-text-blue">KANTOR PT LAPITTA</p>
                                <p class="text-slate-500 mt-1">Gedung API, Lt. 5. Jl. Merdeka No. 51-53, Citarum, Kec. Bandung Wetan, Kota Bandung, Jawa Barat 40115</p>
                                <div class="mt-4 flex items-center gap-2 text-slate-700 bg-blue-50 p-2 rounded w-fit border border-blue-100">
                                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <div class="font-semibold">Limited availability: 120 seats</div>
                                    <div class="text-slate-500 text-xs ml-2">Secure your seat now!</div>
                                </div>
                            </div>
                            <div class="w-40 h-24 bg-slate-200 rounded-lg overflow-hidden border border-slate-300 relative">
                                <img src="https://i.sstatic.net/WHCGy.png" alt="Map Location" class="w-full h-full object-cover">
                            </div>
                        </div>

                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">What you'll learn</h3>
                        <p class="text-xs text-slate-500 mb-8">Knowledge and skills that you will have after finishing program</p>
                        <div class="grid grid-cols-2 gap-4 mb-8">
                            @for($i=0; $i<6; $i++)
                            <div class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <div class="text-sm font-body text-text-gray leading-normal">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus.</div>
                            </div>
                            @endfor
                        </div>

                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">Tools/Software Used</h3>
                        <p class="text-xs text-slate-500 mb-8">In this program you wil use these tools/software</p>
                        <div class="grid gap-4 mb-8">
                            @for($i=0; $i<4; $i++)
                            <div class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <div class="text-sm font-body text-text-gray leading-normal">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce urna massa, aliquet vel odio et, aliquet gravida lorem. Maecenas gravida consequat dolor. Nullam elementum nisi vitae ipsum molestie hendrerit.</div>
                            </div>
                            @endfor
                        </div>

                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">Position that perfect to take this program</h3>
                        <p class="text-xs text-slate-500 mb-8">If your background match position below, this program is specially design for you</p>
                        <div class="grid gap-4 mb-8">
                            @for($i=0; $i<4; $i++)
                            <div class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <div class="text-sm font-body text-text-gray leading-normal">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce urna massa, aliquet vel odio et, aliquet gravida lorem. Maecenas gravida consequat dolor. Nullam elementum nisi vitae ipsum molestie hendrerit.</div>
                            </div>
                            @endfor
                        </div>

                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">Pre-requisites</h3>
                        <p class="text-xs text-slate-500 mb-8">Knowledge and skills that you must have before join this program</p>
                        <div class="grid grid-cols-2 gap-4 mb-8">
                            @for($i=0; $i<6; $i++)
                            <div class="flex items-center gap-2 text-sm text-slate-600">
                                <svg class="w-5 h-5 text-green-500 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                <div class="text-sm font-body text-text-gray leading-normal">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus.</div>
                            </div>
                            @endfor
                        </div>

                        <h3 class="font-heading font-bold text-2xl flex items-center gap-4 text-text-blue mb-1">PDF Brochure</h3>
                        <p class="text-xs text-slate-500 mb-8">Share program to your friends with this brochure</p>
                        <button class="flex items-center gap-4 bg-secondary2 border border-gray2 rounded-lg p-3 pr-16 hover:bg-slate-50 transition">
                            <div class="bg-white border border-gray2 rounded p-2 text-blue-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-800">Brochure.pdf</p>
                                <p class="text-xs text-slate-500">2.4 MB</p>
                            </div>
                        </button>
                    </div>
                </div>

                <div x-data="{ 
                    coursesState: {{ json_encode($initialCourseState) }},
                    sectionsState: {{ json_encode($initialSectionState) }},
                    
                    // Mengecek apakah SEMUA course dan SEMUA section terbuka
                    isAllExpanded() {
                        const allCoursesOpen = Object.values(this.coursesState).every(val => val === true);
                        const allSectionsOpen = Object.values(this.sectionsState).every(val => val === true);
                        return allCoursesOpen && allSectionsOpen;
                    },
                    
                    toggleAll() {
                        // Jika semua sudah terbuka, maka kita ingin 'collapse' (false). Jika tidak, 'expand' (true).
                        let targetState = !this.isAllExpanded();
                        
                        // Atur status Course
                        for (let key in this.coursesState) {
                            // Jika collapse (targetState=false), biarkan ID pertama tetap true (kembali ke default)
                            this.coursesState[key] = targetState ? true : (key === Object.keys(this.coursesState)[0]);
                        }

                        // Atur status Section
                        for (let key in this.sectionsState) {
                            // Jika collapse, semua section menjadi false (tertutup)
                            this.sectionsState[key] = targetState;
                        }
                    }
                }">
                    <h2 class="font-heading text-3xl font-bold text-text-blue mb-2">Your learning path</h2>
                    <p class="font-caption text-xs text-text-blue mb-8">
                        <span>This program contains</span>
                        <span class="font-caption font-bold text-text-blue"> {{ count($courses) }} Courses</span>
                    </p>
                    
                    <div class="flex justify-between items-end mb-4">
                        <p class="text-xs text-slate-500">
                            <span>5</span> Courses • <span>15</span> Sections • <span>40</span> Lessons
                        </p>
                        <button 
                            @click="toggleAll()" 
                            class="text-blue-600 text-sm font-semibold hover:underline"
                            x-text="isAllExpanded() ? 'Collapse all courses' : 'Expand all courses'">
                        </button>
                    </div>

                    @foreach($courses as $course)
                    <div class="bg-white border border-slate-200 rounded-xl mb-3 shadow-sm overflow-hidden">
                        
                        <button @click="coursesState[{{ $course['id'] }}] = !coursesState[{{ $course['id'] }}]" class="w-full flex justify-between items-center p-4 bg-white hover:bg-slate-50 transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-slate-400 transition-transform" :class="{'rotate-90': coursesState[{{ $course['id'] }}]}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span class="font-semibold text-slate-800">Course {{ $loop->iteration }}: {{ $course['title'] }}</span>
                            </div>
                            <span class="text-xs text-slate-500">{{ count($course['sections']) }} Sections</span>
                        </button>

                        <div x-show="coursesState[{{ $course['id'] }}]" x-collapse class="p-4 border-t border-slate-100 bg-slate-50">
                            <div class="px-4">
                                <p class="text-slate-600 mb-4 text-sm">{{ $course['description'] }}</p>
                                <p class="mb-4"><span class="font-body">Instructor : </span><span class="font-body font-bold">{{ $course['instructor'] }}</span></p>
                            </div>

                            @foreach($course['sections'] as $section)
                            <div class="mb-3 bg-white border border-slate-200 rounded-lg overflow-hidden shadow-sm">
                                
                                <button @click="sectionsState[{{ $section['id'] }}] = !sectionsState[{{ $section['id'] }}]" class="w-full flex justify-between items-center p-3 hover:bg-slate-50 transition">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500 transition-transform" :class="{'rotate-90': sectionsState[{{ $section['id'] }}]}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                        <span class="font-medium text-slate-700 text-sm">Section {{ $loop->iteration }}: {{ $section['title'] }}</span>
                                    </div>
                                </button>
                                
                                <div x-show="sectionsState[{{ $section['id'] }}]" x-collapse class="p-3 border-t border-slate-100 text-sm text-slate-600 pl-9">
                                    <ul class="space-y-2">
                                        @foreach($section['lessons'] as $lesson)
                                        <li class="flex gap-2 items-center">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> 
                                            {{ $lesson['type'] }}: {{ $lesson['title'] }}
                                        </li>
                                        @endforeach
                                    </ul>
                                </div>

                            </div>
                            @endforeach

                        </div>
                    </div>
                    @endforeach
                </div>
                
                <div>
                    <h2 class="font-heading text-3xl font-bold text-text-blue mb-8">Meet the Instructor</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-sm flex items-center gap-4">
                            <div class="w-12 h-12 bg-slate-200 rounded-full flex shrink-0 items-center justify-center text-slate-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm">R. Alikhalifi, ST</h4>
                                <p class="text-xs text-slate-500 mb-3">Pakar IT, Software Engineering</p>
                                <p class="text-[10px] text-text-blue font-medium">Instructor for Course: </p>
                                <p class="text-[10px] text-primary font-medium">Introduction to IoT Course</p>
                            </div>
                        </div>
                        <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-sm flex items-center gap-4">
                            <div class="w-12 h-12 bg-slate-200 rounded-full flex shrink-0 items-center justify-center text-slate-400">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                            </div>
                            <div>
                                <h4 class="font-bold text-slate-800 text-sm">F. Akbarudin, Bs.</h4>
                                <p class="text-xs text-slate-500 mb-3">VP of Engineering at Tokopedia</p>
                                <p class="text-[10px] text-text-blue font-medium">Instructor for Course:</p>
                                <p class="text-[10px] text-primary font-medium">Internet of Things Components</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="font-heading text-3xl font-bold text-text-blue mb-8">What students say</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        
                        <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-sm flex items-start gap-4">
                            <div class="w-12 h-12 bg-slate-200 rounded-full flex shrink-0 items-center justify-center text-slate-400 mt-1">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/></svg>
                            </div>
                            <div class="w-full">
                                <div class="flex justify-between items-center mb-2">
                                    <h4 class="font-bold text-slate-800 text-sm">Jihad Fufu Fafa</h4>
                                    
                                    <span class="flex items-center text-sm font-bold text-slate-700">
                                        <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                        4.8
                                    </span>
                                </div>
                                
                                <p class="text-xs text-slate-500 mb-1 pr-2">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus.</p>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 p-4 rounded-xl shadow-sm flex items-start gap-4">
                            <div class="w-12 h-12 bg-slate-200 rounded-full flex shrink-0 items-center justify-center text-slate-400 mt-1">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="w-full">
                                <div class="flex justify-between items-center mb-2">
                                    <h4 class="font-bold text-slate-800 text-sm">Ridwanti</h4>
                                    
                                    <span class="flex items-center text-sm font-bold text-slate-700">
                                        <svg class="w-4 h-4 text-yellow-400 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                        4.8
                                    </span>
                                </div>

                                <p class="text-xs text-slate-500 mb-1 pr-2">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce urna massa, aliquet vel odio et, aliquet gravida lorem. Maecenas gravida consequat dolor. Nullam elementum nisi vitae ipsum molestie hendrerit.</p>
                            </div>
                        </div>

                    </div>
                </div>

                <div x-data="{ active: 1 }" class="mb-4">
                    <h2 class="font-heading text-3xl font-bold text-text-blue mb-8">Frequently Asked Question</h2>
                    <div class="space-y-3 text-sm mb-8">
                        <button @click="active = active === 1 ? null : 1" 
                            class="relative z-10 w-full flex justify-between items-center px-5 py-4 bg-white rounded-lg transition-colors duration-200 focus:outline-none border"
                            :class="active === 1 ? 'border-[#3b82f6]' : 'border-[#60a5fa] hover:border-[#3b82f6]'">
                            <span class="font-semibold text-[#2d3359] text-[15px]">Can I switch plan later ?</span>
                            
                            <svg class="w-3.5 h-3.5 text-[#2d3359] transition-transform duration-300" 
                                :class="active === 1 ? 'rotate-0' : 'rotate-180'" 
                                viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 7l7 10H5l7-10z"/>
                            </svg>
                        </button>

                        <div x-show="active === 1" x-collapse class="relative z-0">
                            <div class="bg-white border-b border-l border-r border-[#e2e8f0] rounded-b-lg -mt-2 pt-6 pb-4 px-5">
                                <p class="text-[#2d3359] font-medium text-[14px]">
                                    Yes of course, you can switch plan anytime.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-3 text-sm mb-8">
                        <button @click="active = active === 2 ? null : 2" 
                            class="relative z-10 w-full flex justify-between items-center px-5 py-4 bg-white rounded-lg transition-colors duration-200 focus:outline-none border"
                            :class="active === 2 ? 'border-[#3b82f6]' : 'border-[#60a5fa] hover:border-[#3b82f6]'">
                            <span class="font-semibold text-[#2d3359] text-[15px]">Is there a refund policy ?</span>
                            
                            <svg class="w-3.5 h-3.5 text-[#2d3359] transition-transform duration-300" 
                                :class="active === 2 ? 'rotate-0' : 'rotate-180'" 
                                viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 7l7 10H5l7-10z"/>
                            </svg>
                        </button>

                        <div x-show="active === 2" x-collapse class="relative z-0">
                            <div class="bg-white border-b border-l border-r border-[#e2e8f0] rounded-b-lg -mt-2 pt-6 pb-4 px-5">
                                <p class="text-[#2d3359] font-medium text-[14px]">
                                    Information regarding our refund policy will be displayed here.
                                </p>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="lg:col-span-1">
                <div class=" top-0 space-y-6">
                    
                    <div class="bg-white rounded-xl shadow-md border border-slate-100 p-6">
                        <h2 class="text-3xl font-extrabold text-blue-600 mb-2">Rp 2.000</h2>
                        <button class="flex items-center gap-2 text-slate-500 text-sm mb-6 hover:text-red-500 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            Add to wishlist
                        </button>
                        
                        <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition shadow-sm shadow-blue-200 mb-6">
                            Enroll Now
                        </button>

                        <h3 class="font-semibold text-slate-800 text-sm mb-3">This Program bundle include :</h3>
                        <ul class="space-y-3 text-sm text-slate-600 mb-6">
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg> 2 Hours On-Demand Videos</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> 15 Articles</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> 10 Assessment</li>
                            <li class="flex items-center gap-3"><svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg> Certificate of Completion</li>
                        </ul>

                        <div class="border-t border-slate-100 pt-4 mt-4">
                            <label class="text-xs text-slate-500 block mb-2">Have enrollment code? Use here to enroll</label>
                            <div class="flex gap-2">
                                <input type="text" class="flex-1 border-slate-200 rounded-md text-sm focus:border-blue-500 focus:ring-blue-500 shadow-sm" placeholder="Code...">
                                <button class="bg-blue-100 text-blue-700 px-4 py-2 rounded-md text-sm font-semibold hover:bg-blue-200 transition">Apply</button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 text-center">
                        <h3 class="font-bold text-slate-800 mb-2">Got questions about the lessons?</h3>
                        <p class="text-xs text-slate-500 mb-6">Don't keep your questions to yourself. Ask, discuss, and connect. Join the forum discussion!</p>
                        
                        <div x-data="{ active: 0 }" class="mb-4">
                            <div class="space-y-3 text-sm mb-8">
                                <button @click="active = active === 1 ? null : 1" 
                                    class="z-10 flex items-center w-full justify-between transition-colors duration-200 focus:outline-none border border-blue-200 rounded-md p-2 text-left relative bg-blue-50/50 px-3"
                                    :class="active === 1 ? 'border-[#3b82f6]' : 'border-[#60a5fa] hover:border-[#3b82f6]'">

                                    <span>
                                        <div class="text-[10px] text-blue-500 block">From Topic: Introduction to IoT Course</div>
                                        <div class="text-sm font-medium text-slate-800">What is OSI Layers?</div>
                                    </span>
                                                                        
                                    <svg class="w-3.5 h-3.5 text-[#2d3359] transition-transform duration-300" 
                                        :class="active === 1 ? 'rotate-0' : 'rotate-180'" 
                                        viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 7l7 10H5l7-10z"/>
                                    </svg>
                                </button>

                                <div x-show="active === 1" x-collapse class="relative z-0">
                                    <div class="bg-white border-b border-l border-r border-[#e2e8f0] rounded-b-lg -mt-2 pt-6 pb-4 px-5">
                                        <p class="text-[#2d3359] font-medium text-[14px]">
                                            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Morbi rhoncus rhoncus turpis id tempus. Nam massa nisl, pretium quis.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-slate-500 mb-4">Let's find other discussion or go ask your questions!</p>
                        <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg transition shadow-sm">
                            Go to Forum
                        </button>
                    </div>

                </div>
            </div>

        </div>
    </div>
