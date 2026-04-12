// Firebase configuration for Stailian
// Uses the compat SDK (loaded via CDN in each HTML page)

const firebaseConfig = {
  apiKey:            "AIzaSyBUdN01FuZYr9Q12pmX57QXkXWZyymTCW8",
  authDomain:        "stailian.firebaseapp.com",
  projectId:         "stailian",
  storageBucket:     "stailian.firebasestorage.app",
  messagingSenderId: "722523255831",
  appId:             "1:722523255831:web:3e131ba7616945b0ab1852",
  measurementId:     "G-2MMEG2LSL0"
};

firebase.initializeApp(firebaseConfig);

window.auth = firebase.auth();
window.db   = firebase.firestore();
