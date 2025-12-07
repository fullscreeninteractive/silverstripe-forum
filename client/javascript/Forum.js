/**
 * Javascript features for the SilverStripe forum module. These have been
 * ported over from the old Prototype system
 *
 * @package forum
 */

(function () {
  document.addEventListener("DOMContentLoaded", function () {
    /**
     * Handle the OpenID information Box.
     * It will open / hide the little popup
     */

    // Helper function to fade out an element
    function fadeOut(element) {
      element.style.transition = "opacity 0.3s";
      element.style.opacity = "0";
      setTimeout(function () {
        element.style.display = "none";
      }, 300);
    }

    // default to hiding the BBTags
    var bbTagsHolder = document.getElementById("BBTagsHolder");
    if (bbTagsHolder) {
      bbTagsHolder.style.display = "none";
      bbTagsHolder.classList.remove("showing");
    }

    var showOpenIDdesc = document.getElementById("ShowOpenIDdesc");
    if (showOpenIDdesc) {
      showOpenIDdesc.addEventListener("click", function (e) {
        e.preventDefault();
        var openIDDescription = document.getElementById("OpenIDDescription");
        if (openIDDescription) {
          if (openIDDescription.classList.contains("showing")) {
            openIDDescription.style.display = "none";
            openIDDescription.classList.remove("showing");
          } else {
            openIDDescription.style.display = "";
            openIDDescription.classList.add("showing");
          }
        }
      });
    }

    var hideOpenIDdesc = document.getElementById("HideOpenIDdesc");
    if (hideOpenIDdesc) {
      hideOpenIDdesc.addEventListener("click", function (e) {
        e.preventDefault();
        var openIDDescription = document.getElementById("OpenIDDescription");
        if (openIDDescription) {
          openIDDescription.style.display = "none";
        }
      });
    }

    /**
     * BBCode Tools
     * While editing / replying to a post you can get a little popup
     * with all the BBCode tags
     */
    var bbCodeHint = document.getElementById("BBCodeHint");
    if (bbCodeHint) {
      bbCodeHint.addEventListener("click", function (e) {
        e.preventDefault();
        var bbTagsHolder = document.getElementById("BBTagsHolder");
        if (bbTagsHolder) {
          if (bbTagsHolder.classList.contains("showing")) {
            this.textContent = "View Formatting Help";
            bbTagsHolder.style.display = "none";
            bbTagsHolder.classList.remove("showing");
          } else {
            this.textContent = "Hide Formatting Help";
            bbTagsHolder.style.display = "";
            bbTagsHolder.classList.add("showing");
          }
        }
      });
    }

    /**
     * MultiFile Uploader called on Reply and Edit Forms
     * Note: MultiFile is a jQuery plugin, so we check if jQuery is available
     */
    var attachmentField = document.getElementById(
      "Form_PostMessageForm_Attachment"
    );
    if (attachmentField) {
      var jQuery = window["jQuery"];
      if (
        typeof jQuery !== "undefined" &&
        jQuery &&
        jQuery.fn &&
        jQuery.fn.MultiFile
      ) {
        jQuery(attachmentField).MultiFile({ namePattern: "$name-$i" });
      }
    }

    /**
     * Delete post Link.
     *
     * Add a popup to make sure user actually wants to do
     * the dirty and remove that wonderful post
     */
    var deleteLinks = document.querySelectorAll(".postModifiers a.deletelink");
    deleteLinks.forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        var first = this.classList.contains("firstPost");

        if (first) {
          if (
            !confirm(
              "Are you sure you wish to delete this thread?\nNote: This will delete ALL posts in this thread."
            )
          )
            return;
        } else {
          if (!confirm("Are you sure you wish to delete this post?")) return;
        }

        fetch(this.getAttribute("href"), {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
        })
          .then(function (response) {
            return response.text();
          })
          .then(function (data) {
            if (first) {
              // if this is the first post then we have removed the entire thread and therefore
              // need to redirect the user to the parent page. To get to the parent page we convert
              // something similar to general-discussion/show/1 to general-discussion/
              var url = window.location.href;
              var pos = url.lastIndexOf("/show");
              if (pos > 0) window.location.href = url.substring(0, pos);
            } else {
              // deleting a single post.
              var singlePost = link.closest(".singlePost");
              if (singlePost) {
                fadeOut(singlePost);
              }
            }
          });
      });
    });

    /**
     * Mark Post as Spam Link.
     * It needs to warn the user that the post will be deleted
     */
    var spamLinks = document.querySelectorAll(
      ".postModifiers a.markAsSpamLink"
    );
    spamLinks.forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        var first = this.classList.contains("firstPost");

        if (
          !confirm(
            "Are you sure you wish to mark this post as spam? This will remove the post, and suspend the user account"
          )
        )
          return;

        fetch(this.getAttribute("href"), {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
        })
          .then(function (response) {
            return response.text();
          })
          .then(function (data) {
            if (first) {
              // if this is the first post then we have removed the entire thread and therefore
              // need to redirect the user to the parent page. To get to the parent page we convert
              // something similar to general-discussion/show/1 to general-discussion/
              var url = window.location.href;
              var pos = url.lastIndexOf("/show");
              if (pos > 0) window.location.href = url.substring(0, pos);
            } else {
              window.location.reload();
            }
          });
      });
    });

    /**
     * Delete an Attachment via AJAX
     */
    var deleteAttachments = document.querySelectorAll("a.deleteAttachment");
    deleteAttachments.forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        if (!confirm("Are you sure you wish to delete this attachment")) return;
        var id = this.getAttribute("rel");

        fetch(this.getAttribute("href"), {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
        })
          .then(function (response) {
            return response.text();
          })
          .then(function (data) {
            var attachment = document.querySelector(
              "#CurrentAttachments li.attachment-" + id
            );
            if (attachment) {
              fadeOut(attachment); // hide the deleted attachment
            }
          });
      });
    });

    /**
     * Subscribe / Unsubscribe button
     *
     * Note: The subscribe and unsubscribe buttons should share a common parent
     */
    var subscribeLinks = document.querySelectorAll("a.subscribe");
    subscribeLinks.forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        var anchor = e.target;
        if (!(anchor instanceof HTMLElement)) return;
        var anchorElement = anchor;
        fetch(this.getAttribute("href"), {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
        })
          .then(function (response) {
            return response.text();
          })
          .then(function (data) {
            if (data == "1") {
              anchorElement.style.display = "none";
              anchorElement.classList.add("hidden");
              var parent = anchorElement.parentElement;
              if (parent) {
                var unsubscribeLink = parent.querySelector("a.unsubscribe");
                if (unsubscribeLink && unsubscribeLink instanceof HTMLElement) {
                  unsubscribeLink.style.display = "";
                  unsubscribeLink.classList.remove("hidden");
                }
              }
            }
          });
      });
    });

    var unsubscribeLinks = document.querySelectorAll("a.unsubscribe");
    unsubscribeLinks.forEach(function (link) {
      link.addEventListener("click", function (e) {
        e.preventDefault();
        var anchor = e.target;
        if (!(anchor instanceof HTMLElement)) return;
        var anchorElement = anchor;
        fetch(this.getAttribute("href"), {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
        })
          .then(function (response) {
            return response.text();
          })
          .then(function (data) {
            if (data == "1") {
              anchorElement.style.display = "none";
              anchorElement.classList.add("hidden");
              var parent = anchorElement.parentElement;
              if (parent) {
                var subscribeLink = parent.querySelector("a.subscribe");
                if (subscribeLink && subscribeLink instanceof HTMLElement) {
                  subscribeLink.style.display = "";
                  subscribeLink.classList.remove("hidden");
                }
              }
            }
          });
      });
    });

    /**
     * Ban / Ghost member confirmation
     */
    var banGhostLinks = document.querySelectorAll("a.banLink, a.ghostLink");
    banGhostLinks.forEach(function (link) {
      link.addEventListener("click", function (e) {
        var action = this.classList.contains("banLink") ? "ban" : "ghost";
        if (
          !confirm(
            "Are you sure you wish to " +
              action +
              " this user? This will hide all posts by this user on all forums"
          )
        ) {
          e.preventDefault();
        }
      });
    });
  });
})();
