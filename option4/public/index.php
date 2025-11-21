<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>SSE Messenger</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
        <link rel="stylesheet" href="assets/styles.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        
        <script src="assets/app.js"></script>
    </head>
    
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <div class="status-bar-left">
                        9:41
                    </div>
                    <div class="status-bar-right">
                        <div id="statusIcon" class="status-icon connected" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Connected"></div>
                        <i class="fas fa-signal"></i>
                        <i class="fas fa-battery-full"></i>
                    </div>
                </div>
                
                <div class="bg-primary text-white" id="chatHeader">
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="h4"><i class="fas fa-comments"></i> Messenger</h4>
                        <span class="badge" title="Click to change name">
                            <span id="clientName">You</span>
                        </span>
                    </div>
                    <div class="active-users" id="activeUsers"></div>
                </div>
                
                <div class="card-body" id="chatContainer"></div>
                
                <div class="card-footer" id="chatInput">
                    <form>
                        <div class="d-flex gap-2">
                            <textarea 
                                id="inp" 
                                class="form-control" 
                                rows="1" 
                                placeholder="Type a message..."></textarea>
                            <button 
                                id="sendBtn"
                                type="submit" 
                                class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
