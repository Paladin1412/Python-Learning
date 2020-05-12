import subprocess
import sys
import getopt
import logging

logging.basicConfig(level=logging.INFO,
                    format='%(asctime)s %(levelname)s %(message)s', )


def call_sys(command):
    ret = subprocess.Popen(command, shell=True, stdout=subprocess.PIPE)
    return ret.stdout.read().decode('utf-8')


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
    ret = call_sys("docker pull jenkins/jenkins:{}".format(version))
    logging.info(ret)
    logging.info("Try to push to local registry...")
    ret = call_sys("docker tag jenkins/jenkins:{} 192.168.11.3:10000/home/jenkins:{}".format(version, version))
    logging.info(ret)
    ret = call_sys("docker push 192.168.11.3:10000/home/jenkins:{}".format(version))
    logging.info(ret)
    logging.info("Changing jenkins pod image...")
    ret = call_sys(
        "kubectl set image -n ci --record deployment jenkins-ci jenkins-ci=192.168.11.3:10000/home/jenkins:{}".format(
            version))
    logging.info(ret)
    ret = call_sys("kubectl rollout status deploy jenkins-ci -n ci")
    logging.info(ret)


if __name__ == '__main__':
    start(sys.argv[1:])
