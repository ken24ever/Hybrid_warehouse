<?php
// --- 1. SERVER-SIDE SESSION HANDLING (The Fail-Safe) ---
session_start();

// Capture Session Data
$my_branch_code = $_SESSION['branch_code'] ?? '';
$my_branch_name = $_SESSION['branch_name'] ?? 'My Local Branch';

// Construct the Offline Link using PHP directly (No JS needed for this)
$offline_link = "superAdmin.php?branch_uuid=" . urlencode($my_branch_code) . 
                "&branch_code=" . urlencode($my_branch_code) . 
                "&branch_name=" . urlencode($my_branch_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Hub | Jemerald Stores</title>
    
    <link rel="stylesheet" href="bootstrap_v4/css/bootstrap.min.css">
    <link rel="stylesheet" href="fontawesome-free-7.1.0-web/css/all.min.css">
    
    <style>
        /* Anti-Flicker: Hides Alpine elements until they are ready */
        [x-cloak] { display: none !important; }

        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        
        .hub-header {
            background: linear-gradient(135deg, #1a2980 0%, #26d0ce 100%);
            color: white; padding: 40px 0; margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1); position: relative;
            transition: background 0.5s ease;
        }
        
        /* Dynamic Header Background based on status */
        .hub-header.mode-offline {
            background: linear-gradient(135deg, #3E5151 0%, #DECBA4 100%); /* Grey/Amber for Offline */
        }

        .branch-card {
            border: none; border-radius: 12px; background: white;
            transition: all 0.3s ease; overflow: hidden; margin-bottom: 25px;
        }
        .branch-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
        
        /* Status Indicators */
        .status-indicator {
            position: absolute; top: 20px; right: 20px; width: 12px; height: 12px; border-radius: 50%;
        }
        .status-online {
            background-color: #28a745; box-shadow: 0 0 0 4px rgba(40, 167, 69, 0.2);
            animation: pulse 2s infinite;
        }
        .status-offline { background-color: #ffc107; box-shadow: 0 0 0 4px rgba(255, 193, 7, 0.2); }
        
        .metric-value { font-size: 1.2rem; font-weight: 600; color: #34495e; }
        .btn-view {
            border-radius: 50px; padding: 8px 25px; font-weight: 600;
            background-color: #f8f9fa; color: #1a2980; border: 1px solid #e9ecef;
        }
        .btn-view:hover { background-color: #1a2980; color: white; }
        
        /* Live Feed Badge */
        .live-badge {
            position: absolute; top: 20px; right: 20px;
            background: rgba(255, 255, 255, 0.2); padding: 5px 12px;
            border-radius: 20px; font-size: 0.8rem; font-weight: 600;
            display: flex; align-items: center; backdrop-filter: blur(5px);
        }
        
        .live-dot {
            width: 8px; height: 8px; border-radius: 50%; margin-right: 8px;
        }
        
        /* Cloud Mode (Green Pulse) */
        .dot-cloud {
            background-color: #00ff88;
            box-shadow: 0 0 10px #00ff88;
            animation: blink 1s infinite;
        }
        
        /* Local Mode (Amber Static) */
        .dot-local {
            background-color: #ffc107;
            border: 1px solid rgba(255,255,255,0.5);
        }

        @keyframes pulse { 0% { transform: scale(0.95); opacity: 0.7; } 70% { transform: scale(1); opacity: 0; } 100% { transform: scale(0.95); opacity: 0; } }
        @keyframes blink { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
    
    
    /* Logout Button Styling */
.logout-btn {
    position: absolute; /* Takes it out of the flow */
    top: 20px;
    left: 25px;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 600;
    text-decoration: none;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    z-index: 100; /* Ensures it stays on top */
    display: flex;
    align-items: center;
}

.logout-btn:hover {
    color: #ffffff;
    text-decoration: none;
    transform: translateX(-3px); /* Subtle slide effect */
}

.logout-btn i {
    font-size: 1.1rem;
    margin-right: 8px;
}
    </style>

<style>
        /* Container for the buttons */
        .header-controls-left {
            position: absolute;
            top: 20px;
            left: 25px;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 15px; /* Perfect spacing between buttons */
        }

        /* Button Styling */
        .header-control-btn {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 600;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1); /* Glassmorphism background */
            padding: 8px 16px;
            border-radius: 30px; /* Fully rounded pill shape */
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(4px);
        }

        .header-control-btn:hover {
            color: #ffffff;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .header-control-btn i {
            font-size: 1rem;
        }

        /* Responsive adjustments for mobile */
        @media (max-width: 576px) {
            .header-controls-left {
                top: 15px;
                left: 15px;
                gap: 10px;
            }
            .header-control-btn {
                padding: 8px; /* Icon only on mobile */
            }
            .header-control-btn span {
                display: none;
            }
        }
    </style>

    <style>
        .ceo-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            border: 1px solid rgba(0,0,0,0.02);
            overflow: hidden;
        }
        .ceo-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            color: white;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            box-shadow: 0 5px 15px rgba(253, 160, 133, 0.4);
        }
        .deco-circle {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            opacity: 0.05;
        }
        .circle-1 { width: 150px; height: 150px; top: -50px; left: -50px; }
        .circle-2 { width: 100px; height: 100px; bottom: -30px; right: -20px; }
        .z-1 { z-index: 1; }
    </style>
    
    <script src="jquery/jquery-3.6.0.min.js"></script>
    <script src="bootstrap_v4/js/bootstrap.min.js"></script>
    <script src="js/alpine.min.js" defer></script>

</head> 
<body>

    <div x-data="branchManager()" x-init="initLiveUpdate()" class="container-fluid p-0">
        
<div class="hub-header text-center position-relative" :class="{'mode-offline': source === 'local'}">
    
<div class="header-controls-left">
        
        <a href="logout.php" class="header-control-btn" title="Sign out of the system">
            <i class="fas fa-sign-out-alt"></i> 
            <span class="d-none d-sm-inline ml-2">Logout</span>
        </a>

        <a href="test_connection.php" class="header-control-btn text-warning" title="Run connectivity diagnostics">
            <i class="fas fa-stethoscope"></i> 
            <span class="d-none d-sm-inline ml-2">Server Status</span>
        </a>

        <?php if (isset($_SESSION['branch_code']) && $_SESSION['branch_code'] === 'HOME_OFFICE'): ?>
        <a href="setup_ceo.php" class="header-control-btn text-info" title="Manage CEO Login Credentials">
            <i class="fas fa-user-shield"></i> 
            <span class="d-none d-sm-inline ml-2">CEO Access</span>
        </a>
        <?php endif; ?>

    </div>

    <div class="live-badge">
        <div class="live-dot" :class="source === 'cloud' ? 'dot-cloud' : 'dot-local'"></div>
        <span x-text="source === 'cloud' ? 'LIVE CLOUD FEED' : 'OFFLINE MODE (LOCAL)'">OFFLINE MODE (LOCAL)</span>
    </div>
    
    <h2 class="font-weight-bold">Enterprise Control Hub</h2>
    <p class="opacity-75" x-text="source === 'cloud' ? 'Real-time overview from Cloud Server' : 'Displaying locally cached data'">Displaying locally cached data</p>
    
    <div class="d-flex justify-content-center mt-4" x-show="source === 'cloud'" x-cloak x-transition>
        <div class="input-group" style="max-width: 500px;">
            <div class="input-group-prepend">
                <span class="input-group-text border-0 bg-white pl-4"><i class="fas fa-search text-muted"></i></span>
            </div>
            <input type="text" x-model="search" class="form-control border-0 py-4" placeholder="Find a branch..." style="border-radius: 0 50px 50px 0; border-top-left-radius: 0; border-bottom-left-radius: 0;">
        </div>
    </div>

</div>
<br>
<?php if (isset($_SESSION['branch_code']) && $_SESSION['branch_code'] === 'HOME_OFFICE'): ?>
    <div class="container" style="margin-top: -30px; position: relative; z-index: 10;">
        <div class="card border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="card-body p-4" style="background: linear-gradient(to right, #ffffff, #f8f9fa); border-left: 5px solid #6a11cb;">
                <div class="d-flex align-items-center">
                    
                    <div class="mr-4 d-none d-sm-block">
                        <div style="width: 70px; height: 70px; background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 5px 15px rgba(37, 117, 252, 0.3);">
                            <i class="fas fa-user-tie text-white fa-2x"></i>
                        </div>
                    </div>
                    
                    <div class="flex-grow-1">
                        <h5 class="text-uppercase text-primary font-weight-bold mb-1" style="letter-spacing: 1px; font-size: 0.85rem;">Executive Overview</h5>
                        <h2 class="font-weight-bold text-dark mb-1">Welcome, CEO</h2>
                        <p class="text-muted mb-0">
                            Global Enterprise Monitoring is <span class="badge badge-success px-2 py-1 ml-1" style="font-size: 0.8rem;">ACTIVE</span>
                        </p>
                    </div>

                    <div class="text-right d-none d-md-block opacity-25">
                        <i class="fas fa-globe-africa fa-4x text-primary"></i>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <br>

        <div class="container pb-5">
            
            <div class="row justify-content-center" x-show="source === 'local'">
                <div class="col-lg-4 col-md-6">
                    <div class="card branch-card h-100 p-3" style="border: 2px solid #ffc107;">
                        <div class="status-indicator status-offline" title="Offline Mode"></div>
                        
                        <div class="card-body">
                            <div class="mb-4">
                                <h5 class="card-title"><?php echo htmlspecialchars($my_branch_name); ?> (Local)</h5>
                                <div class="card-subtitle text-muted">
                                    <i class="fas fa-database mr-1 text-warning"></i> 
                                    <span>Local Database Mode</span>
                                </div>
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="alert alert-warning p-2" style="font-size: 0.85rem;">
                                        <i class="fas fa-wifi-slash mr-1"></i> 
                                        You are viewing the local version of your branch. Cloud data is currently unavailable.
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($my_branch_code)): ?>
                                <a href="<?php echo $offline_link; ?>" 
                                   class="btn btn-view btn-block stretched-link">
                                   Access Local Dashboard <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            <?php else: ?>
                                <button disabled class="btn btn-danger btn-block">
                                    Session Expired - Please Relogin
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row" x-show="source === 'cloud'" style="display: none;" x-cloak x-transition>
                
                <div x-show="loading" class="col-12 text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-3 text-muted">Fetching Cloud Data...</p>
                </div>

                <template x-for="branch in filteredBranches" :key="branch.uuid">
    <div class="col-md-4 mb-4" x-show="true" x-transition>
        <div class="card shadow-sm h-100 border-0 branch-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title font-weight-bold mb-0 text-dark" x-text="branch.name"></h5>
                    <span class="badge badge-light text-secondary border" x-text="branch.uuid"></span>
                </div>
                
                <p class="card-text text-muted mb-3">
                    <i class="fas fa-map-marker-alt mr-2 text-primary"></i>
                    <span x-text="branch.location"></span>
                </p>

                <div class="d-flex align-items-center mb-2">
                    <span class="d-inline-block rounded-circle mr-2"
                          :class="branch.status === 'online' ? 'bg-success' : 'bg-secondary'"
                          style="width: 10px; height: 10px;">
                    </span>
                    <small class="text-muted" x-text="branch.status === 'online' ? 'Online' : 'Offline'"></small>
                </div>

                <div style="font-size: 0.85rem; color: #666; margin-bottom: 15px;">
                    <i class="fas fa-history mr-1"></i> 
                    Last active: <span x-text="branch.last_sync" class="font-weight-bold"></span>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                    <div>
                        <small class="text-uppercase text-muted font-weight-bold" style="font-size: 0.7rem;">Today's Sales</small>
                        <div class="h5 font-weight-bold text-success mb-0" x-text="branch.sales_today"></div>
                    </div>
                    
                    <a :href="getCloudLink(branch)" 
                       class="btn btn-sm btn-outline-primary rounded-pill px-3"
                >
                        Manage <i class="fas fa-arrow-right ml-1"></i>
                    </a>

             
                </div>
            </div>
        </div>
    </div>
</template>

                <div x-show="!loading && branches.length > 0 && filteredBranches.length === 0" class="col-12 text-center py-5">
                    <h4 class="text-muted">No branches found matching "<span x-text="search"></span>"</h4>
                </div>
            </div>
        </div>

    </div>
    
    <script>
        function branchManager() {
            return {
                search: '',
                branches: [],
                loading: false,
                source: 'local', // Default to Local to match PHP render
                
                initLiveUpdate() {
                    // Immediate check on load
                    this.fetchStats();
                    // Poll every 5 seconds
                    setInterval(() => { this.fetchStats(); }, 5000);
                },

 

                fetchStats() {
                    // [FIX] Browser Check
                    if (!navigator.onLine) {
                        if (this.source !== 'local') this.source = 'local';
                        return;
                    }

                    const uniqueUrl = 'api/get_live_stats.php?_=' + new Date().getTime(); 

                    fetch(uniqueUrl, { cache: "no-store" })
                        .then(res => {
                            if (!res.ok) throw new Error("API Unreachable: " + res.status);
                            return res.json();
                        })
                        .then(response => {
                            if (response.error) {
                                console.warn('API Error:', response.error);
                                this.source = 'local';
                            } else {
                                // [CRITICAL FIX] Don't assume 'cloud'. Use what the API tells us.
                                // If DBManager fell back to local, response.meta.source will be 'local'.
                                this.source = response.meta && response.meta.source ? response.meta.source : 'cloud';
                                this.branches = response.data;
                            }
                        })
                        .catch(err => {
                            console.log('Network/Fetch Failed:', err.message);
                            this.source = 'local'; 
                        });
                },
                
                // [LOGIC]: Simple Cloud Link generator (Online Only) 
                getCloudLink(branch) {
                    return 'superAdmin.php?branch_uuid=' + branch.uuid + 
                           '&branch_code=' + branch.uuid + 
                           '&branch_name=' + encodeURIComponent(branch.name);
                },

                get filteredBranches() {
                    if (this.search === '') return this.branches;
                    return this.branches.filter(branch => {
                        return branch.name.toLowerCase().includes(this.search.toLowerCase()) || 
                               branch.location.toLowerCase().includes(this.search.toLowerCase());
                    });
                }
            }
        }
    </script>
</body>
</html>