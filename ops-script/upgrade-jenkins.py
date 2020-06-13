import subprocess
import sys
import getopt
import logging

logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s %(levelname)s %(message)s', )


def start(argv):
    version = 'latest'
    logging.info("Init...")
    try:
        opts, args = getopt.getopt(argv, "hv:", ["version="])
    except getopt.GetoptError:
        sys.exit(2)
    for opt, args in opts:
        if opt == '-v':
            version = args
    logging.info("Get version: {}".format(version))
    logging.info("Try to pull from registry...")
    subprocess.check_call(["docker", "pull", "jenkins/jenkins:{}".format(version)])
    logging.info("Try to push to local registry...")
    subprocess.check_call(
        ["docker", "tag", "jenkins/jenkins:{}".format(version), "192.168.11.3:10000/home/jenkins:{}".format(version)])
    subprocess.check_call(["docker", "push", "192.168.11.3:10000/home/jenkins:{}".format(version)])
    logging.info("Changing jenkins pod image...")
    subprocess.check_call(
        ["kubectl", "set", "image", "-n", "ci", "--record", "deployment", "jenkins-ci",
         "jenkins-ci=192.168.11.3:10000/home/jenkins:{}".format(
             version)])
    subprocess.check_call("kubectl rollout status deploy jenkins-ci -n ci")
    logging.info("SUCCESS!")


if __name__ == '__main__':
    start(sys.argv[1:])
