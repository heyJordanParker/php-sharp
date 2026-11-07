// Starts `sh -c 'exec 0<&-; echo x >&2; exec sleep 30'` with a pipe on its stdin and its stderr, as the closed-stdin
// test does, from one thread that starts nothing else. Once the child's stderr line arrives, the child has closed its
// stdin, so the read end of the stdin pipe should be gone. Counts the runs where it is still alive, and how long it
// lives on.
#include <errno.h>
#include <fcntl.h>
#include <libproc.h>
#include <signal.h>
#include <spawn.h>
#include <stdio.h>
#include <stdlib.h>
#include <sys/proc_info.h>
#include <sys/wait.h>
#include <time.h>
#include <unistd.h>

extern char **environ;

static unsigned long long peer(int fd) {
    struct pipe_fdinfo info;
    if (proc_pidfdinfo(getpid(), fd, PROC_PIDFDPIPEINFO, &info, sizeof info) != (int)sizeof info) {
        return ~0ULL;
    }
    return info.pipeinfo.pipe_peerhandle;
}

static double now_us(void) {
    struct timespec time;
    clock_gettime(CLOCK_MONOTONIC, &time);
    return time.tv_sec * 1e6 + time.tv_nsec / 1e3;
}

int main(int argc, char **argv) {
    int runs = argc > 1 ? atoi(argv[1]) : 200;
    int alive = 0;
    double longest = 0;
    signal(SIGPIPE, SIG_IGN);
    for (int run = 0; run < runs; run++) {
        int in[2], err[2];
        if (pipe(in) || pipe(err)) { perror("pipe"); return 1; }
        fcntl(in[0], F_SETFD, FD_CLOEXEC); fcntl(in[1], F_SETFD, FD_CLOEXEC);
        fcntl(err[0], F_SETFD, FD_CLOEXEC); fcntl(err[1], F_SETFD, FD_CLOEXEC);
        posix_spawn_file_actions_t actions;
        posix_spawn_file_actions_init(&actions);
        posix_spawn_file_actions_adddup2(&actions, in[0], 0);
        posix_spawn_file_actions_adddup2(&actions, err[1], 2);
        char *child[] = {"sh", "-c", "exec 0<&-; echo x >&2; exec sleep 30", NULL};
        pid_t pid;
        if (posix_spawn(&pid, "/bin/sh", &actions, NULL, child, environ)) { perror("posix_spawn"); return 1; }
        posix_spawn_file_actions_destroy(&actions);
        close(in[0]);
        close(err[1]);
        char line[8];
        if (read(err[0], line, sizeof line) <= 0) { fprintf(stderr, "no stderr in run %d\n", run); return 1; }
        if (peer(in[1]) != 0) {
            alive++;
            double started = now_us();
            while (peer(in[1]) != 0 && now_us() - started < 3e6) { usleep(100); }
            double lived = now_us() - started;
            if (lived > longest) { longest = lived; }
            ssize_t wrote = write(in[1], "x", 1);
            printf("run %d: the read end lived on %.0f us after the stderr line; a write then returned %zd (%s)\n",
                   run, lived, wrote, wrote < 0 ? "Broken pipe expected" : "accepted");
        }
        kill(pid, SIGKILL);
        waitpid(pid, NULL, 0);
        close(in[1]);
        close(err[0]);
    }
    printf("%d of %d runs: the read end of the child's stdin outlived its close, the longest by %.0f us\n", alive, runs,
           longest);
    return 0;
}
